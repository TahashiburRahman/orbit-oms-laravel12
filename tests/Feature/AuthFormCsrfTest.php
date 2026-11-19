<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthFormCsrfTest extends TestCase
{
    use RefreshDatabase;

    private array $browserCookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['session.driver' => 'database', 'session.lottery' => [0, 100]]);
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    private function employee(bool $temporary = true): User
    {
        return User::factory()->create([
            'email' => 'csrf-fixture@example.test',
            'team_id' => Team::where('slug', 'admin')->value('id'),
            'password' => 'Temporary-csrf-fixture-password',
            'must_change_password' => $temporary,
        ]);
    }

    private function browser(string $method, string $url, array $data = [], bool $json = false): TestResponse
    {
        // A real browser sends encrypted cookies to a new request, not a cached guard/session.
        Auth::forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        Facade::clearResolvedInstance('session');

        $response = $this->call($method, $url, $data, $this->browserCookies, [], [
            'HTTP_ACCEPT' => $json ? 'application/json' : 'text/html',
        ]);
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

    private function login(User $user): TestResponse
    {
        $page = $this->browser('GET', route('login'))->assertOk();
        $this->browser('POST', route('login.store'), [
            '_token' => $this->token($page),
            'email' => $user->email,
            'password' => 'Temporary-csrf-fixture-password',
        ])->assertRedirect(route('dashboard'));

        return $this->browser('GET', route('password.change'));
    }

    private function replacement(array $extra = []): array
    {
        return [
            '_method' => 'put',
            'current_password' => 'Temporary-csrf-fixture-password',
            'password' => 'A-replacement-csrf-fixture-password',
            'password_confirmation' => 'A-replacement-csrf-fixture-password',
            ...$extra,
        ];
    }

    public function test_fresh_login_and_password_form_succeed_with_real_csrf_and_database_session_cookies(): void
    {
        $user = $this->employee();
        $login = $this->browser('GET', route('login'))->assertOk();
        $this->assertStringContainsString('no-store', $login->headers->get('Cache-Control'));
        $loginToken = $this->token($login);
        $this->browser('POST', route('login.store'), ['_token' => $loginToken, 'email' => $user->email, 'password' => 'Temporary-csrf-fixture-password'])
            ->assertRedirect(route('dashboard'));
        $form = $this->browser('GET', route('password.change'))->assertOk();
        $token = $this->token($form);
        $this->assertNotSame($loginToken, $token);
        $this->browser('POST', route('password.update'), $this->replacement(['_token' => $token]))->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('A-replacement-csrf-fixture-password', $user->fresh()->password));
        $this->browser('GET', route('dashboard'))->assertRedirect(route('dashboard.team', 'admin'));
        $this->browser('GET', route('dashboard.team', 'admin'))->assertOk();
    }

    public static function invalidTokens(): array
    {
        return ['missing' => [null], 'stale' => ['stale-fixture-token']];
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_password_form_token_recovers_without_mutation_or_secret_flash_and_fresh_retry_succeeds(?string $token): void
    {
        $user = $this->employee();
        $hash = $user->password;
        $this->login($user)->assertOk();
        $payload = $this->replacement($token === null ? [] : ['_token' => $token]);
        $response = $this->browser('POST', route('password.update'), $payload)->assertStatus(303)->assertRedirect(route('password.change'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue($user->fresh()->must_change_password);
        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $response->assertSessionMissing('_old_input.'.$field);
            $response->assertDontSee($payload[$field]);
        }
        $fresh = $this->browser('GET', route('password.change'))->assertOk();
        $this->browser('POST', route('password.update'), $this->replacement(['_token' => $this->token($fresh)]))->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_expired_database_session_recovers_to_login_without_changing_the_password(): void
    {
        $user = $this->employee();
        $hash = $user->password;
        $form = $this->login($user)->assertOk();
        $token = $this->token($form);
        DB::table('sessions')->where('id', $this->app['session']->getId())->delete();
        $this->browser('POST', route('password.update'), $this->replacement(['_token' => $token]))->assertStatus(303)->assertRedirect(route('login'));
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue($user->fresh()->must_change_password);
        $this->browser('GET', route('login'))->assertOk();
    }

    public function test_json_and_unrelated_mutations_still_receive_419_for_invalid_csrf(): void
    {
        $user = $this->employee();
        $this->login($user)->assertOk();
        $this->browser('POST', route('password.update'), $this->replacement(['_token' => 'invalid-fixture-token']), true)->assertStatus(419);
        $this->browser('POST', route('clients.store'), ['_token' => 'invalid-fixture-token', 'name' => 'Rejected fixture client', 'status' => 'active'])->assertStatus(419);
        $this->assertDatabaseCount('clients', 0);
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_stale_login_and_logout_forms_recover_without_replaying_the_action(): void
    {
        $user = $this->employee();
        $this->browser('GET', route('login'))->assertOk();
        $this->browser('POST', route('login.store'), ['_token' => 'old-login-token', 'email' => $user->email, 'password' => 'Temporary-csrf-fixture-password'])
            ->assertStatus(303)->assertRedirect(route('login'));
        $this->login($user)->assertOk();
        $this->browser('POST', route('logout'), ['_token' => 'old-logout-token'])->assertStatus(303)->assertRedirect(route('dashboard'));
        $this->browser('GET', route('password.change'))->assertOk();
    }

    public function test_an_employee_with_an_existing_password_can_recover_and_retry_a_stale_password_form(): void
    {
        $user = $this->employee(false);
        $this->login($user)->assertOk();
        $hash = $user->password;
        $this->browser('POST', route('password.update'), $this->replacement(['_token' => 'old-setup-token']))->assertStatus(303)->assertRedirect(route('password.change'));
        $this->assertSame($hash, $user->fresh()->password);
        $fresh = $this->browser('GET', route('password.change'))->assertOk();
        $this->browser('POST', route('password.update'), $this->replacement(['_token' => $this->token($fresh)]))->assertRedirect(route('dashboard'));
        $this->assertTrue(Hash::check('A-replacement-csrf-fixture-password', $user->fresh()->password));
    }
}
