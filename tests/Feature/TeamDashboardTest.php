<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function team(string $slug): int
    {
        return (int) Team::where('slug', $slug)->value('id');
    }

    private function staff(string $primary, array $additional = []): User
    {
        $user = User::factory()->create(['team_id' => $this->team($primary)]);
        $user->teams()->syncWithoutDetaching(array_map(fn ($slug) => $this->team($slug), $additional));

        return $user;
    }

    private function updateData(User $user, array $overrides = []): array
    {
        return array_replace(['name' => $user->name, 'email' => $user->email, 'team_id' => $user->team_id, 'active' => 1], $overrides);
    }

    public function test_all_dual_team_patterns_can_open_both_dashboards_and_keep_the_primary_default(): void
    {
        foreach ([['admin', 'developer'], ['operations', 'developer'], ['marketing', 'graphics']] as [$primary, $additional]) {
            $user = $this->staff($primary, [$additional]);
            $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('dashboard.team', $primary));
            foreach ([$primary, $additional] as $slug) {
                $this->get(route('dashboard.team', $slug))->assertOk()->assertSee('Switch dashboard')->assertSee(route('dashboard.team', $primary), false)->assertSee(route('dashboard.team', $additional), false);
            }
            $this->assertSame($this->team($primary), $user->fresh()->team_id);
            $this->get(route('dashboard'))->assertRedirect(route('dashboard.team', $primary));
        }
    }

    public function test_single_team_staff_cannot_open_an_unassigned_dashboard_directly(): void
    {
        $user = $this->staff('graphics');
        $this->actingAs($user)->get(route('dashboard.team', 'graphics'))->assertOk()->assertDontSee('Switch dashboard');
        foreach (['admin', 'operations', 'developer', 'marketing'] as $slug) {
            $this->get(route('dashboard.team', $slug))->assertForbidden();
        }
        $this->assertSame($this->team('graphics'), $user->fresh()->team_id);
    }

    public function test_switching_dashboards_keeps_membership_permissions_and_payroll_authorization(): void
    {
        $user = $this->staff('marketing', ['graphics']);
        $this->actingAs($user)->get(route('dashboard.team', 'graphics'))->assertOk()->assertSee('Leads')->assertSee('Creative briefs')->assertDontSee('People &amp; teams', false);
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('payroll.index'))->assertForbidden();
        $this->get(route('dashboard.team', 'admin'))->assertForbidden();

        $operationsMember = $this->staff('developer', ['operations']);
        $this->actingAs($operationsMember)->get(route('dashboard.team', 'operations'))->assertOk();
        $this->get(route('payroll.index'))->assertForbidden();
        $operationsMember->update(['payroll_authorized' => true]);
        $this->get(route('payroll.index'))->assertOk();
        $this->get(route('dashboard.team', 'developer'))->assertOk();
        $this->get(route('payroll.index'))->assertOk();
    }

    public function test_nonprimary_admin_has_admin_permissions_and_cannot_remove_the_last_admin_membership(): void
    {
        $admin = $this->staff('developer', ['admin']);
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->get(route('audit.index'))->assertOk();
        $this->get(route('dashboard.team', 'admin'))->assertOk();
        $this->get(route('dashboard.team', 'marketing'))->assertForbidden();
        $this->put(route('users.update', $admin), $this->updateData($admin, ['team_ids' => [$this->team('developer')]]))->assertSessionHasErrors('active');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_admin_can_manage_memberships_and_phone_while_primary_is_always_included(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Example Employee', 'email' => ' MULTI@EXAMPLE.TEST ', 'phone' => '+8801700000001', 'team_id' => $this->team('developer'), 'team_ids' => [$this->team('operations')], 'password' => 'A-new-member-password', 'password_confirmation' => 'A-new-member-password', 'active' => 1, 'payroll_authorized' => 1])->assertRedirect();
        $employee = User::where('email', 'multi@example.test')->firstOrFail();
        $this->assertSame('01700000001', $employee->phone);
        $this->assertTrue($employee->inTeam('operations', 'developer'));
        $this->assertCount(2, $employee->teamIds());
        $this->assertTrue($employee->canPayroll());
        $this->get(route('users.edit', $employee))->assertOk()->assertSee('Team memberships');
        $this->get(route('users.show', $employee))->assertOk()->assertSee('Additional teams')->assertSee('Operations');
        $this->put(route('users.update', $employee), $this->updateData($employee, ['team_id' => $this->team('marketing'), 'team_ids' => [$this->team('graphics')], 'payroll_authorized' => 1]))->assertRedirect();
        $employee->refresh();
        $this->assertTrue($employee->inTeam('marketing'));
        $this->assertTrue($employee->inTeam('graphics'));
        $this->assertFalse($employee->inTeam('developer', 'operations'));
        $this->assertFalse($employee->canPayroll());
        $this->assertDatabaseHas('audit_logs', ['subject_type' => User::class, 'subject_id' => $employee->id, 'event' => 'team_memberships_changed']);
    }

    public function test_legacy_account_updates_preserve_additional_memberships_and_explicit_empty_selection_removes_them(): void
    {
        $admin = $this->staff('admin');
        $employee = $this->staff('operations', ['developer']);
        $this->actingAs($admin)->put(route('users.update', $employee), $this->updateData($employee, ['team_id' => $this->team('marketing')]))->assertRedirect();
        $employee->refresh();
        $this->assertTrue($employee->inTeam('developer'));
        $this->assertTrue($employee->inTeam('marketing'));
        $this->put(route('users.update', $employee), $this->updateData($employee, ['memberships_present' => 1]))->assertRedirect();
        $employee->refresh();
        $this->assertSame([$this->team('marketing')], $employee->teamIds());
        $this->assertGreaterThanOrEqual(2, AuditLog::where('subject_type', User::class)->where('subject_id', $employee->id)->where('event', 'team_memberships_changed')->count());
    }
}
