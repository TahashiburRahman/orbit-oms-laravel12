<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $team, array $attrs = []): User
    {
        return User::factory()->create([...$attrs, 'team_id' => Team::where('slug', $team)->value('id')]);
    }

    public function test_all_five_teams_login_and_redirect_to_their_dashboard(): void
    {
        foreach (['admin', 'operations', 'marketing', 'graphics', 'developer'] as $slug) {
            $user = $this->person($slug, ['password' => 'A-local-test-password']);
            $this->post('/login', ['email' => $user->email, 'password' => 'A-local-test-password'])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->get('/dashboard')->assertRedirect('/dashboard/'.$slug);
            $this->get('/dashboard/'.$slug)->assertOk();
            $this->post('/logout')->assertRedirect('/login');
            $this->assertGuest();
        }
    }

    public function test_registration_is_disabled_and_inactive_accounts_cannot_login(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $user = $this->person('developer', ['active' => false, 'password' => 'A-local-test-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'A-local-test-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/tasks')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_create_accounts_and_non_admin_cannot_manage_them(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin)->post('/users', ['name' => 'Nadia Rahman', 'email' => 'nadia@example.test', 'team_id' => Team::where('slug', 'marketing')->value('id'), 'password' => 'A-new-local-password', 'password_confirmation' => 'A-new-local-password', 'active' => 1])->assertRedirect();
        $employee = User::where('email', 'nadia@example.test')->firstOrFail();
        $this->assertTrue($employee->active);
        $this->assertTrue($employee->inTeam('marketing'));
        $this->actingAs($employee)->get('/users')->assertForbidden();
        $this->put('/users/'.$admin->id, ['name' => 'Changed'])->assertForbidden();
    }

    public function test_last_admin_and_audit_entries_are_protected(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin)->put('/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'team_id' => Team::where('slug', 'operations')->value('id'), 'active' => 1])->assertSessionHasErrors('active');
        $log = AuditLog::firstOrFail();
        $this->expectException(\LogicException::class);
        $log->update(['event' => 'tampered']);
    }

    public function test_login_rate_limit_blocks_repeated_attempts(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', ['email' => 'missing@example.test', 'password' => 'bad']);
        }
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('email'));
    }

    public function test_first_admin_command_uses_hidden_password_input(): void
    {
        $this->artisan('oms:admin', ['--name' => 'Local Owner', '--email' => 'owner@example.test'])->expectsQuestion('Password (at least 12 characters)', 'A-long-test-password')->expectsQuestion('Confirm password', 'A-long-test-password')->assertSuccessful();
        $this->assertTrue(User::where('email', 'owner@example.test')->firstOrFail()->isAdmin());
    }
}
