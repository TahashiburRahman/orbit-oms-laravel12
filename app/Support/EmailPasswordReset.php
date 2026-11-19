<?php

namespace App\Support;

use App\Mail\PasswordResetOtp as ResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailPasswordReset
{
    public function send(string $email, string $ip): string
    {
        app(OtpMailReadiness::class)->ensureAvailable();
        $identity = hash('sha256', $email);
        $emailKey = 'password-reset:email:'.$identity;
        $cooldownKey = 'password-reset:cooldown:'.$identity;
        $ipKey = 'password-reset:ip:'.hash('sha256', $ip);
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            throw ValidationException::withMessages(['email' => 'Please wait a minute before requesting another code.']);
        }
        if (RateLimiter::tooManyAttempts($emailKey, config('password_reset.email_requests_per_hour', 3))
            || RateLimiter::tooManyAttempts($ipKey, config('password_reset.ip_requests_per_hour', 10))) {
            throw ValidationException::withMessages(['email' => 'Too many reset-code requests. Please try again later.']);
        }
        RateLimiter::hit($cooldownKey, config('password_reset.resend_cooldown_seconds', 60));
        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        $challenge = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        $recipient = DB::transaction(function () use ($email, $challenge, $code) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (! $user || ! $user->active || ! $user->team_id) {
                return null;
            }
            $existing = PasswordResetOtp::where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing && $existing->sent_at->addSeconds(config('password_reset.resend_cooldown_seconds', 60))->isFuture()) {
                throw ValidationException::withMessages(['email' => 'Please wait a minute before requesting another code.']);
            }
            PasswordResetOtp::updateOrCreate(['user_id' => $user->id], [
                'challenge' => $challenge,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'sent_at' => now(),
                'expires_at' => now()->addMinutes(config('password_reset.expires_minutes', 10)),
            ]);

            return $user;
        });

        if ($recipient) {
            try {
                Mail::to($recipient->email)->send(new ResetOtpMail($code, config('password_reset.expires_minutes', 10)));
            } catch (Throwable) {
                PasswordResetOtp::where('user_id', $recipient->id)->where('challenge', $challenge)->delete();
                // SMTP exceptions can include message content or connection credentials.
                // Keep the browser response identical to an unknown-account request.
                Log::warning('Password-reset email delivery failed. Check the configured SMTP service.');
            }
        }

        return $challenge;
    }

    public function reset(string $email, string $challenge, string $code, string $password): void
    {
        $result = DB::transaction(function () use ($email, $challenge, $code, $password) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            if (! $user || ! $user->active || ! $user->team_id) {
                return 'invalid';
            }
            $otp = PasswordResetOtp::where('user_id', $user->id)->where('challenge', $challenge)->lockForUpdate()->first();
            if (! $otp) {
                return 'invalid';
            }
            if ($otp->expires_at->lte(now()) || $otp->attempts >= config('password_reset.max_attempts', 5)) {
                $otp->delete();

                return 'invalid';
            }
            if (! Hash::check($code, $otp->code_hash)) {
                $otp->attempts++;
                if ($otp->attempts >= config('password_reset.max_attempts', 5)) {
                    $otp->delete();
                } else {
                    $otp->save();
                }

                return 'invalid';
            }
            if (Hash::check($password, $user->password)) {
                return 'same_password';
            }

            $user->forceFill(['password' => Hash::make($password), 'password_changed_at' => now(), 'must_change_password' => false, 'remember_token' => null])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $otp->delete();
            Audit::record($user, 'password_reset_by_email', ['method' => 'email_otp']);

            return 'success';
        });
        // Report failures after the transaction commits so wrong guesses cannot roll back their counters.
        if ($result === 'same_password') {
            throw ValidationException::withMessages(['password' => 'Choose a new password different from your current password.']);
        }
        if ($result !== 'success') {
            throw ValidationException::withMessages(['otp' => 'The code is invalid or expired. Request a new code if needed.']);
        }
    }
}
