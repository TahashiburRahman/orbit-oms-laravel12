<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperadminAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function person(string $slug = 'admin', bool $super = false): User
    {
        $user = User::factory()->create(['team_id' => Team::where('slug', $slug)->value('id')]);
        if ($super) $user->forceFill(['is_superadmin' => true])->save();

        return $user;
    }

    private function data(User $u, array $extra = []): array
    {
        return array_replace(['name' => $u->name, 'email' => $u->email, 'team_id' => $u->team_id, 'active' => 1], $extra);
    }

    private function profile(User $u): Employee
    {
        return Employee::create(['user_id' => $u->id, 'employee_code' => 'EMP-'.$u->id, 'job_title' => 'Employee', 'joined_on' => '2026-01-01', 'base_salary' => '1000.00']);
    }

    public function test_role_is_not_mass_assignable_and_admin_cannot_grant_or_edit_superadmin(): void
    {
        $admin = $this->person();
        $owner = $this->person('developer', true);
        $member = $this->person('graphics');
        $member->fill(['is_superadmin' => true]);
        $this->assertFalse($member->isSuperAdmin());
        $this->actingAs($admin)->put(route('users.update', $member), $this->data($member, ['is_superadmin' => 1]))->assertForbidden();
        $this->get(route('users.edit', $owner))->assertForbidden();
        $this->put(route('users.update', $owner), $this->data($owner, ['name' => 'Altered']))->assertForbidden();
        $this->post(route('users.store'), $this->data($member, ['email' => 'new@example.test', 'is_superadmin' => 1, 'password' => 'New-account-password', 'password_confirmation' => 'New-account-password']))->assertForbidden();
        $this->assertDatabaseCount('users', 3);
        $this->assertSame($owner->name, $owner->fresh()->name);
    }

    public function test_superadmin_without_admin_membership_manages_all_workspaces_and_can_promote_accounts(): void
    {
        $owner = $this->person('graphics', true);
        $member = $this->person('developer');
        $client = Client::create(['name' => 'Scoped Customer', 'created_by' => $member->id]);
        $this->assertTrue($owner->isAdmin());
        $this->assertTrue($owner->canPayroll());
        $this->assertCount(1, $owner->teamIds());
        $this->actingAs($owner)->get(route('clients.edit', $client))->assertOk();
        foreach (Team::pluck('slug') as $slug) $this->get(route('dashboard.team', $slug))->assertOk();
        $this->put(route('users.update', $member), $this->data($member, ['is_superadmin' => 1]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->isSuperAdmin());
        $this->post(route('users.store'), $this->data($member, ['email' => 'new-super@example.test', 'is_superadmin' => 1, 'password' => 'New-account-password', 'password_confirmation' => 'New-account-password']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'new-super@example.test')->firstOrFail()->isSuperAdmin());
    }

    public function test_last_superadmin_and_own_account_cannot_be_removed_or_demoted(): void
    {
        $owner = $this->person('admin', true);
        $this->actingAs($owner)->put(route('users.update', $owner), $this->data($owner, ['is_superadmin' => 0]))->assertSessionHasErrors('is_superadmin');
        $this->put(route('users.update', $owner), $this->data($owner, ['active' => 0]))->assertSessionHasErrors();
        $this->delete(route('users.destroy', $owner))->assertSessionHasErrors('account');
        $this->assertTrue($owner->fresh()->isSuperAdmin());
        $owner->forceFill(['active' => false])->save();
        $this->assertFalse($owner->isSuperAdmin());
    }

    public function test_account_removal_deletes_profile_draft_entries_memberships_and_sessions_only(): void
    {
        $owner = $this->person('admin', true);
        $member = $this->person('developer');
        $employee = $this->profile($member);
        $run = PayrollRun::create(['period' => '2026-11', 'status' => 'draft', 'created_by' => $owner->id]);
        $entry = PayrollEntry::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'employee_name' => $member->name, 'employee_code' => $employee->employee_code, 'base_salary' => '1000.00', 'allowances' => '0.00', 'deductions' => '0.00', 'net_pay' => '1000.00']);
        DB::table('sessions')->insert(['id' => 'delete-me', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($owner)->get(route('users.show', $member))->assertOk()->assertSee('Delete account');
        $this->delete(route('users.destroy', $member))->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
        $this->assertModelMissing($member);
        $this->assertModelMissing($employee);
        $this->assertModelMissing($entry);
        $this->assertDatabaseHas('payroll_runs', ['id' => $run->id]);
        $this->assertDatabaseMissing('team_user', ['user_id' => $member->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'delete-me']);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => User::class, 'subject_id' => $member->id, 'event' => 'account_deleted']);
    }

    public function test_referenced_account_delete_rolls_back_and_regular_admin_cannot_delete_profiles(): void
    {
        $owner = $this->person('admin', true);
        $admin = $this->person();
        $member = $this->person('developer');
        $profile = $this->profile($member);
        $client = Client::create(['name' => 'Authored Customer', 'created_by' => $member->id]);
        $this->actingAs($admin)->delete(route('users.destroy', $member))->assertForbidden();
        $this->delete(route('employees.destroy', $profile))->assertForbidden();
        $this->actingAs($owner)->delete(route('users.destroy', $member))->assertSessionHasErrors('account');
        $this->assertModelExists($member);
        $this->assertModelExists($profile);
        $this->assertModelExists($client);
        $this->assertDatabaseHas('team_user', ['user_id' => $member->id]);
    }

    public function test_protected_payroll_prevents_employee_delete_but_unused_profile_can_be_removed(): void
    {
        $owner = $this->person('admin', true);
        $member = $this->person('developer');
        $profile = $this->profile($member);
        $run = PayrollRun::create(['period' => '2026-11', 'created_by' => $owner->id]);
        PayrollEntry::create(['payroll_run_id' => $run->id, 'employee_id' => $profile->id, 'employee_name' => 'Fixture employee', 'employee_code' => $profile->employee_code, 'base_salary' => '1000.00', 'net_pay' => '1000.00']);
        $run->update(['status' => 'approved']);
        $this->actingAs($owner)->delete(route('employees.destroy', $profile))->assertSessionHasErrors('employee');
        $unused = $this->profile($this->person('graphics'));
        $this->delete(route('employees.destroy', $unused))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($unused);
        $this->assertModelExists($unused->user);
        $this->assertModelExists($profile);
    }

    public function test_an_audit_actor_is_retained_and_superadmin_role_changes_revoke_sessions(): void
    {
        $owner = $this->person('admin', true);
        $other = $this->person('developer', true);
        $profile = $this->profile($other);
        DB::table('audit_logs')->insert(['user_id' => $other->id, 'subject_type' => User::class, 'subject_id' => $other->id, 'event' => 'fixture_history', 'created_at' => now()]);
        $this->actingAs($owner)->delete(route('users.destroy', $other))->assertSessionHasErrors('account');
        $this->assertModelExists($other);
        $this->assertModelExists($profile);
        DB::table('sessions')->insert(['id' => 'revoke-super', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);
        $this->put(route('users.update', $other), $this->data($other, ['is_superadmin' => 0]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->isSuperAdmin());
        $this->assertTrue($owner->fresh()->isSuperAdmin());
        $this->assertDatabaseMissing('sessions', ['id' => 'revoke-super']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $other->id, 'event' => 'fixture_history']);
    }

    public function test_superadmin_creation_uses_hidden_password_prompts_and_normalized_email(): void
    {
        $this->artisan('oms:superadmin', ['--name' => 'Workspace Owner', '--email' => 'OWNER@EXAMPLE.TEST'])
            ->expectsQuestion('Password (at least 12 characters)', 'Strong-fixture-password')
            ->expectsQuestion('Confirm password', 'Strong-fixture-password')->assertSuccessful();
        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($owner->isSuperAdmin());
        $this->assertTrue(Hash::check('Strong-fixture-password', $owner->password));
        $this->assertSame(0, Artisan::call('oms:verify-dashboard-access'));
        $output = Artisan::output();
        $this->assertStringContainsString('Accounts checked: 1', $output);
        $this->assertStringContainsString('Assigned dashboards rendered: 5', $output);
    }
}
