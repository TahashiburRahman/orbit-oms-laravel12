<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtp as PasswordResetOtpMail;
use App\Models\AuditLog;
use App\Models\PasswordResetOtp;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        Mail::fake();
    }

    private function employee(string $email = 'otp-employee@example.test', bool $active = true): User
    {
        return User::factory()->create([
            'email' => $email,
            'team_id' => Team::where('slug', 'marketing')->value('id'),
            'password' => 'An-existing-fixture-password',
            'remember_token' => 'fixture-remember-token',
            'active' => $active,
        ]);
    }

    private function requestCode(User $user, ?string $email = null): string
    {
        $this->post(route('password.email'), ['email' => $email ?? $user->email])
            ->assertRedirect(route('password.reset'));
        $mail = Mail::sent(PasswordResetOtpMail::class)->last();
        $this->assertNotNull($mail);
        $this->assertMatchesRegularExpression('/^\d{6}$/D', $mail->code);

        return $mail->code;
    }

    private function resetData(User $user, string $code, array $overrides = []): array
    {
        return array_replace([
            'email' => $user->email,
            'otp' => $code,
            'password' => 'A-new-fixture-reset-password',
            'password_confirmation' => 'A-new-fixture-reset-password',
        ], $overrides);
    }

    public function test_email_is_normalized_and_only_a_hash_of_the_delivered_code_is_stored(): void
    {
        $user = $this->employee();
        $code = $this->requestCode($user, '  '.strtoupper($user->email).'  ');
        Mail::assertSent(PasswordResetOtpMail::class, fn ($mail) => $mail->hasTo($user->email));
        $otp = PasswordResetOtp::where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));
        $this->assertSame(0, $otp->attempts);
        $this->assertTrue($otp->expires_at->isFuture());
        $this->assertLessThanOrEqual(601, now()->diffInSeconds($otp->expires_at));
        $this->assertSame($otp->challenge, session('reset_challenge'));
        $this->assertSame($user->email, session('reset_email'));
        $this->assertStringNotContainsString($code, $otp->toJson());
        $this->assertStringNotContainsString($otp->code_hash, $otp->toJson());
        $this->assertGuest();
    }

    public function test_unknown_and_inactive_accounts_receive_the_same_response_without_email_or_database_challenges(): void
    {
        $active = $this->employee();
        $inactive = $this->employee('inactive-otp@example.test', false);
        $this->requestCode($active);
        $status = session('status');
        $this->post(route('password.email'), ['email' => 'missing-otp@example.test'])
            ->assertRedirect(route('password.reset'))->assertSessionHas('status', $status);
        $this->post(route('password.email'), ['email' => $inactive->email])
            ->assertRedirect(route('password.reset'))->assertSessionHas('status', $status);
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('password_reset_otps', 1);
        $this->assertDatabaseMissing('password_reset_otps', ['user_id' => $inactive->id]);
    }

    public function test_valid_email_code_changes_only_its_account_revokes_sessions_and_cannot_be_replayed(): void
    {
        $user = $this->employee();
        $user->teams()->attach(Team::where('slug', 'graphics')->value('id'));
        $other = $this->employee('other-otp@example.test');
        $otherHash = $other->password;
        $memberships = $user->teamIds();
        foreach ([$user, $other] as $person) {
            DB::table('sessions')->insert([
                'id' => 'fixture-session-'.$person->id,
                'user_id' => $person->id,
                'payload' => base64_encode('fixture session payload'),
                'last_activity' => now()->timestamp,
            ]);
        }

        $code = $this->requestCode($user);
        $challenge = session('reset_challenge');
        $this->post(route('password.reset.store'), $this->resetData($user, $code))
            ->assertRedirect(route('login'))->assertSessionHas('status');
        $changed = $user->fresh();
        $this->assertTrue(Hash::check('A-new-fixture-reset-password', $changed->password));
        $this->assertFalse(Hash::check('An-existing-fixture-password', $changed->password));
        $this->assertNotNull($changed->password_changed_at);
        $this->assertFalse($changed->must_change_password);
        $this->assertNull($changed->remember_token);
        $this->assertSame($memberships, $changed->teamIds());
        $this->assertSame($user->team_id, $changed->team_id);
        $this->assertSame($otherHash, $other->fresh()->password);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
        $this->assertDatabaseCount('password_reset_otps', 0);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => User::class, 'subject_id' => $user->id, 'event' => 'password_reset_by_email']);
        $this->assertGuest();

        $audit = AuditLog::where('subject_type', User::class)->where('subject_id', $user->id)->get()->toJson();
        foreach ([$code, $changed->password, 'A-new-fixture-reset-password'] as $secret) {
            $this->assertStringNotContainsString($secret, $audit);
        }
        $this->withSession(['reset_email' => $user->email, 'reset_challenge' => $challenge])
            ->post(route('password.reset.store'), $this->resetData($user, $code))
            ->assertSessionHasErrors('otp');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'A-new-fixture-reset-password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_expired_code_does_not_change_the_password(): void
    {
        $user = $this->employee();
        $hash = $user->password;
        $code = $this->requestCode($user);
        $this->travel(11)->minutes();
        $this->post(route('password.reset.store'), $this->resetData($user, $code))->assertSessionHasErrors('otp');
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertGuest();
        $this->travelBack();
    }

    public function test_five_incorrect_codes_consume_the_challenge_and_a_correct_code_then_fails(): void
    {
        $user = $this->employee();
        $hash = $user->password;
        $code = $this->requestCode($user);
        $wrong = $code === '000000' ? '999999' : '000000';
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('password.reset.store'), $this->resetData($user, $wrong))->assertSessionHasErrors('otp');
            if ($attempt < 5) {
                $this->assertSame($attempt, PasswordResetOtp::where('user_id', $user->id)->firstOrFail()->attempts);
            }
        }
        $this->assertDatabaseMissing('password_reset_otps', ['user_id' => $user->id]);
        $this->post(route('password.reset.store'), $this->resetData($user, $code))->assertSessionHasErrors('otp');
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_resend_cooldown_preserves_the_current_challenge_and_resending_invalidates_the_old_challenge(): void
    {
        $user = $this->employee();
        $oldCode = $this->requestCode($user);
        $oldChallenge = session('reset_challenge');
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasErrors('email');
        $this->assertSame($oldChallenge, PasswordResetOtp::where('user_id', $user->id)->firstOrFail()->challenge);
        Mail::assertSentCount(1);

        $this->travel(61)->seconds();
        $newCode = $this->requestCode($user);
        $newChallenge = session('reset_challenge');
        $this->assertNotSame($oldChallenge, $newChallenge);
        $this->assertDatabaseCount('password_reset_otps', 1);
        $this->withSession(['reset_email' => $user->email, 'reset_challenge' => $oldChallenge])
            ->post(route('password.reset.store'), $this->resetData($user, $oldCode))->assertSessionHasErrors('otp');
        $this->withSession(['reset_email' => $user->email, 'reset_challenge' => $newChallenge])
            ->post(route('password.reset.store'), $this->resetData($user, $newCode))->assertRedirect(route('login'));
        Mail::assertSentCount(2);
        $this->travelBack();
    }

    public function test_email_request_rate_limit_stops_repeated_delivery_even_after_the_resend_cooldown(): void
    {
        $user = $this->employee();
        for ($request = 0; $request < 3; $request++) {
            $this->requestCode($user);
            $this->travel(61)->seconds();
        }
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasErrors('email');
        Mail::assertSentCount(3);
        $this->travelBack();
    }

    public function test_ip_request_limit_applies_across_different_email_addresses(): void
    {
        for ($request = 0; $request < 10; $request++) {
            $this->post(route('password.email'), ['email' => 'missing-fixture-'.$request.'@example.test'])
                ->assertRedirect(route('password.reset'));
        }
        $this->post(route('password.email'), ['email' => 'one-more-missing@example.test'])
            ->assertSessionHasErrors('email');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_an_account_deactivated_after_the_email_was_sent_cannot_reset_its_password(): void
    {
        $user = $this->employee();
        $hash = $user->password;
        $code = $this->requestCode($user);
        $user->update(['active' => false]);
        $this->post(route('password.reset.store'), $this->resetData($user, $code))->assertSessionHasErrors('otp');
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertFalse($user->fresh()->active);
    }

    public function test_email_and_browser_challenge_must_match_the_requested_account(): void
    {
        $user = $this->employee();
        $other = $this->employee('not-the-recipient@example.test');
        $hashes = [$user->password, $other->password];
        $code = $this->requestCode($user);
        $this->post(route('password.reset.store'), $this->resetData($user, $code, ['email' => $other->email]))
            ->assertSessionHasErrors('otp');
        $this->withSession(['reset_email' => $user->email, 'reset_challenge' => 'unrelated-fixture-challenge'])
            ->post(route('password.reset.store'), $this->resetData($user, $code))
            ->assertSessionHasErrors('otp');
        $this->assertSame($hashes, [$user->fresh()->password, $other->fresh()->password]);
    }

    public function test_password_validation_does_not_consume_the_code_or_flash_secrets(): void
    {
        $user = $this->employee();
        $code = $this->requestCode($user);
        $this->from(route('password.reset'))->post(route('password.reset.store'), $this->resetData($user, $code, ['password' => 'short', 'password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');
        $this->assertSame(0, PasswordResetOtp::where('user_id', $user->id)->firstOrFail()->attempts);
        foreach (['otp', 'password', 'password_confirmation'] as $secret) {
            $this->assertNull(session()->getOldInput($secret));
        }
        $this->post(route('password.reset.store'), $this->resetData($user, $code))->assertRedirect(route('login'));
    }

    public function test_changing_password_from_the_dashboard_revokes_any_pending_email_code(): void
    {
        $user = $this->employee();
        $code = $this->requestCode($user);
        $challenge = session('reset_challenge');
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'fixture-old-reset-token', 'created_at' => now()]);
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'An-existing-fixture-password',
            'password' => 'A-voluntary-fixture-password',
            'password_confirmation' => 'A-voluntary-fixture-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('password_reset_otps', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->withSession(['reset_email' => $user->email, 'reset_challenge' => $challenge])
            ->post(route('password.reset.store'), $this->resetData($user, $code))->assertSessionHasErrors('otp');
        $this->assertTrue(Hash::check('A-voluntary-fixture-password', $user->fresh()->password));
    }

    public function test_an_admin_password_reset_revokes_any_pending_email_code(): void
    {
        $user = $this->employee();
        $admin = User::factory()->create(['email' => 'otp-admin@example.test', 'team_id' => Team::where('slug', 'admin')->value('id')]);
        $code = $this->requestCode($user);
        $challenge = session('reset_challenge');
        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'team_id' => $user->team_id,
            'active' => 1,
            'password' => 'An-admin-set-fixture-password',
            'password_confirmation' => 'An-admin-set-fixture-password',
        ])->assertRedirect(route('users.show', $user));
        $this->assertDatabaseMissing('password_reset_otps', ['user_id' => $user->id]);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->withSession(['reset_email' => $user->email, 'reset_challenge' => $challenge])
            ->post(route('password.reset.store'), $this->resetData($user, $code))->assertSessionHasErrors('otp');
        $this->assertTrue(Hash::check('An-admin-set-fixture-password', $user->fresh()->password));
    }

    public function test_failed_email_delivery_clears_the_challenge_and_returns_only_a_sanitized_error(): void
    {
        $user = $this->employee();
        $hash = $user->password;
        config(['mail.default' => 'smtp', 'mail.from.address' => 'sender@example.test', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587, 'mail.mailers.smtp.scheme' => 'smtp']);
        Log::spy();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('PRIVATE-FIXTURE-SMTP-DETAIL'));
        $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.reset'))->assertSessionHasNoErrors()->assertSessionHas('status');
        $status = session('status');
        $response->assertDontSee('PRIVATE-FIXTURE-SMTP-DETAIL');
        $this->assertStringNotContainsString('PRIVATE-FIXTURE-SMTP-DETAIL', $status);
        $this->assertDatabaseCount('password_reset_otps', 0);
        $this->assertSame($hash, $user->fresh()->password);
        Log::shouldHaveReceived('warning')->once()->with('Password-reset email delivery failed. Check the configured SMTP service.');
        Log::shouldNotHaveReceived('error');
        $this->post(route('password.email'), ['email' => 'missing-after-delivery-failure@example.test'])
            ->assertRedirect(route('password.reset'))->assertSessionHas('status', $status);
        $this->assertGuest();
    }

    public function test_non_delivering_log_transport_is_refused_outside_the_test_environment(): void
    {
        $user = $this->employee();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app->instance('env', 'local');
        config(['mail.default' => 'log']);
        try {
            $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasErrors('email');
            $this->assertDatabaseCount('password_reset_otps', 0);
            Mail::assertNothingSent();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
