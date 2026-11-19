<?php

namespace App\Http\Controllers;

use App\Support\EmailPasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ForgotPasswordController extends Controller
{
    public function create()
    {
        return response()->view('auth.forgot-password')->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $request, EmailPasswordReset $broker)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|email|max:255']);
        try {
            $challenge = $broker->send($data['email'], $request->ip());
        } catch (QueryException) {
            return back()->withErrors(['email' => 'Password recovery is temporarily unavailable. Please try again later.']);
        } catch (RuntimeException) {
            return back()->withErrors(['email' => 'Email delivery is not configured. Please contact your administrator.']);
        }
        $request->session()->put(['reset_email' => $data['email'], 'reset_challenge' => $challenge]);

        return redirect()->route('password.reset')->with('status', 'Check your inbox and spam folder for a reset code if this email matches an active employee account. If no code arrives, try again or contact your administrator.');
    }

    public function edit(Request $request)
    {
        if (! $request->session()->has('reset_email') || ! $request->session()->has('reset_challenge')) {
            return redirect()->route('password.request');
        }

        return response()->view('auth.reset-password', ['email' => $request->session()->get('reset_email'), 'expiresMinutes' => config('password_reset.expires_minutes', 10)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function reset(Request $request, EmailPasswordReset $broker)
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'otp' => ['required', 'string', 'regex:/^\d{6}$/D'],
            'password' => ['bail', 'required', 'string', 'confirmed', Password::min(12), function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The new password must not exceed 72 bytes.');
                }
            }],
        ]);
        if ($data['email'] !== $request->session()->get('reset_email') || ! is_string($request->session()->get('reset_challenge'))) {
            throw ValidationException::withMessages(['otp' => 'The code is invalid or expired. Request a new code if needed.']);
        }
        try {
            $broker->reset($data['email'], $request->session()->get('reset_challenge'), $data['otp'], $data['password']);
        } catch (QueryException) {
            return back()->withErrors(['otp' => 'Password recovery is temporarily unavailable. Please try again later.']);
        }
        $request->session()->forget(['reset_email', 'reset_challenge']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }
}
