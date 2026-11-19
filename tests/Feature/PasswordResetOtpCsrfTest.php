<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtp as PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetOtpCsrfTest extends TestCase
{
    use RefreshDatabase;

    private array $browserCookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        Mail::fake();
        config(['session.driver' => 'database', 'session.lottery' => [0, 100]]);
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    private function employee(): User
    {
        return User::factory()->create([
            'email' => 'otp-csrf-fixture@example.test',
            'team_id' => Team::where('slug', 'marketing')->value('id'),
            'password' => 'An-existing-csrf-fixture-password',
        ]);
    }

    private function browser(string $method, string $url, array $data = []): TestResponse
    {
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        Facade::clearResolvedInstance('session');
        $response = $this->call($method, $url, $data, $this->browserCookies, [], ['HTTP_ACCEPT' => 'text/html']);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->browserCookies[$cookie->getName()] = $cookie->getValue();
        }

        return $response;
    }

    private function token(TestResponse $response): string
    {
        $this->assertSame(1, preg_match('/name="_token"\s+value="([^"]+)"/', $response->getContent(), $matches));

        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    private function requestCode(User $user): array
    {
        $form = $this->browser('GET', route('password.request'))->assertOk();
        $this->browser('POST', route('password.email'), ['_token' => $this->token($form), 'email' => $user->email])
            ->assertRedirect(route('password.reset'));
        $mail = Mail::sent(PasswordResetOtpMail::class)->last();
        $this->assertNotNull($mail);

        return [$mail->code, $this->browser('GET', route('password.reset'))->assertOk()];
    }

    private function replacement(User $user, string $code, array $extra = []): array
    {
        return [
            'email' => $user->email,
            'otp' => $code,
            'password' => 'A-replacement-otp-csrf-password',
            'password_confirmation' => 'A-replacement-otp-csrf-password',
            ...$extra,
        ];
    }

    public function test_email_otp_reset_completes_with_real_csrf_and_encrypted_database_session_cookies(): void
    {
        $user = $this->employee();
        [$code, $form] = $this->requestCode($user);
        $this->assertStringContainsString('no-store', $form->headers->get('Cache-Control'));
        $this->browser('POST', route('password.reset.store'), $this->replacement($user, $code, ['_token' => $this->token($form)]))
            ->assertRedirect(route('login'));
        $this->assertDatabaseCount('password_reset_otps', 0);
        $this->assertTrue(Hash::check('A-replacement-otp-csrf-password', $user->fresh()->password));
        $this->assertGuest();
        $login = $this->browser('GET', route('login'))->assertOk();
        $this->browser('POST', route('login.store'), [
            '_token' => $this->token($login), 'email' => $user->email, 'password' => 'A-replacement-otp-csrf-password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_stale_email_request_recovers_to_a_fresh_form_without_sending_a_code(): void
    {
        $user = $this->employee();
        $this->browser('GET', route('password.request'))->assertOk();
        $response = $this->browser('POST', route('password.email'), ['_token' => 'stale-email-fixture-token', 'email' => $user->email])
            ->assertStatus(303)->assertRedirect(route('password.request'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_otps', 0);
        $this->browser('GET', route('password.request'))->assertOk();
    }

    public static function invalidTokens(): array
    {
        return ['missing' => [null], 'stale' => ['stale-reset-fixture-token']];
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_reset_token_preserves_the_password_and_code_and_allows_a_fresh_retry(?string $token): void
    {
        $user = $this->employee();
        $hash = $user->password;
        [$code] = $this->requestCode($user);
        $payload = $this->replacement($user, $code, $token === null ? [] : ['_token' => $token]);
        $response = $this->browser('POST', route('password.reset.store'), $payload)
            ->assertStatus(303)->assertRedirect(route('password.reset'));
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(0, PasswordResetOtp::where('user_id', $user->id)->firstOrFail()->attempts);
        foreach (['otp', 'password', 'password_confirmation'] as $field) {
            $response->assertSessionMissing('_old_input.'.$field);
            $response->assertDontSee($payload[$field]);
        }
        $fresh = $this->browser('GET', route('password.reset'))->assertOk();
        $this->browser('POST', route('password.reset.store'), $this->replacement($user, $code, ['_token' => $this->token($fresh)]))
            ->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('A-replacement-otp-csrf-password', $user->fresh()->password));
    }
}
