<?php

namespace Tests\Feature;

use App\Models\Brief;
use App\Models\Client;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\ReleaseNote;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\ReleasePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTeamAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function staff(string $primary, array $additional = [], array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['team_id' => Team::where('slug', $primary)->value('id'), 'active' => true]);
        $user->teams()->syncWithoutDetaching(Team::whereIn('slug', [$primary, ...$additional])->pluck('id')->all());

        return $user;
    }

    private function client(User $owner): Client
    {
        return Client::create(['name' => 'Fictional Client', 'company' => 'Example Commerce', 'status' => 'active', 'created_by' => $owner->id, 'assigned_to' => $owner->id]);
    }

    public function test_secondary_membership_grants_its_team_records_without_revealing_unassigned_teams(): void
    {
        $owner = $this->staff('admin');
        $dual = $this->staff('marketing', ['graphics']);
        $developer = $this->staff('developer');
        $client = $this->client($owner);
        $project = Project::create(['name' => 'Graphics project', 'client_id' => $client->id, 'team_id' => Team::where('slug', 'graphics')->value('id'), 'created_by' => $owner->id, 'status' => 'active']);
        $visible = Task::create(['title' => 'Secondary graphics team task', 'team_id' => $project->team_id, 'created_by' => $owner->id, 'work_type' => 'general', 'status' => 'open']);
        $hidden = Task::create(['title' => 'Unrelated engineering plan', 'team_id' => $developer->team_id, 'created_by' => $owner->id, 'assigned_to' => $developer->id, 'work_type' => 'general', 'status' => 'open']);

        $this->actingAs($dual)->get(route('projects.show', $project))->assertOk();
        $this->get(route('clients.show', $client))->assertOk();
        $this->get(route('tasks.index'))->assertOk()->assertSee($visible->title)->assertDontSee($hidden->title);
        $this->get(route('tasks.show', $hidden))->assertForbidden();
        $this->get(route('tasks.edit', $visible))->assertOk();
        $this->put(route('tasks.update', $visible), ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $visible->fresh()->status);
    }

    public function test_nonprimary_admin_has_existing_admin_rights_and_secondary_operations_needs_payroll_permission(): void
    {
        $admin = $this->staff('developer', ['admin']);
        $operations = $this->staff('developer', ['operations']);
        $this->actingAs($admin)->get(route('audit.index'))->assertOk();
        $this->get(route('employees.index'))->assertOk();
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($operations->canPayroll());
        $this->actingAs($operations)->get(route('employees.index'))->assertForbidden();
        $this->get(route('payroll.index'))->assertForbidden();
        $operations->update(['payroll_authorized' => true]);
        $this->assertTrue($operations->fresh()->canPayroll());
        $this->actingAs($operations->fresh())->get(route('employees.index'))->assertOk();
    }

    public function test_secondary_graphics_members_can_receive_claim_and_produce_briefs(): void
    {
        $marketer = $this->staff('marketing');
        $creative = $this->staff('marketing', ['graphics']);
        $payload = ['title' => 'Secondary team design brief', 'type' => 'graphic', 'description' => 'Design a fictional launch image.', 'assigned_to' => $creative->id];
        $this->actingAs($marketer)->post(route('briefs.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $brief = Brief::firstOrFail();
        $this->assertSame($creative->id, $brief->assigned_to);
        $this->assertTrue($brief->canProduce($creative));

        $open = Brief::create(['title' => 'Available graphics task', 'type' => 'graphic', 'description' => 'Fictional creative work.', 'requested_by' => $marketer->id, 'status' => 'open']);
        $this->actingAs($creative)->get(route('briefs.show', $open))->assertOk();
        $this->post(route('briefs.claim', $open))->assertRedirect();
        $this->assertSame($creative->id, $open->fresh()->assigned_to);
    }

    public function test_development_assignment_and_partial_progress_work_for_secondary_developer(): void
    {
        $ops = $this->staff('operations');
        $dual = $this->staff('operations', ['developer']);
        $client = $this->client($ops);
        $this->actingAs($ops)->post(route('tasks.store'), ['title' => 'Secondary developer domain setup', 'client_id' => $client->id, 'team_id' => Team::where('slug', 'developer')->value('id'), 'assigned_to' => $dual->id, 'work_type' => 'domain', 'priority' => 'normal', 'status' => 'open'])->assertSessionHasNoErrors()->assertRedirect();
        $task = Task::firstOrFail();
        $this->actingAs($dual)->put(route('tasks.update', $task), ['status' => 'in_progress'])->assertSessionHasNoErrors()->assertRedirect();
        $this->put(route('tasks.update', $task), ['status' => 'submitted', 'configuration_summary' => 'DNS mapping configured.', 'handover_notes' => 'Please verify DNS resolution.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('submitted', $task->fresh()->status);
        $this->actingAs($ops)->put(route('tasks.update', $task), ['status' => 'verified', 'verification_results' => 'DNS resolution verified.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('verified', $task->fresh()->status);
    }

    public function test_releases_target_all_memberships_and_notify_each_user_once(): void
    {
        $admin = $this->staff('admin');
        $creative = $this->staff('marketing', ['graphics']);
        $unrelated = $this->staff('developer');
        $release = ReleaseNote::create(['version' => 'test-multi-team-1', 'summary' => 'Creative release', 'details' => 'A fictional release for creative staff.', 'status' => 'published', 'publish_at' => now()->subMinute(), 'created_by' => $admin->id]);
        $release->targetTeams()->sync(Team::whereIn('slug', ['marketing', 'graphics'])->pluck('id')->all());
        app(ReleasePublisher::class)->notify($release);
        app(ReleasePublisher::class)->notify($release);
        $this->assertSame(1, $creative->notifications()->count());
        $this->assertSame(0, $unrelated->notifications()->count());
        $this->actingAs($creative)->get(route('releases.show', $release))->assertOk();
        $this->actingAs($unrelated)->get(route('releases.show', $release))->assertForbidden();

        $onlyGraphics = ReleaseNote::create(['version' => 'test-graphics-only-1', 'summary' => 'Graphics-only release', 'details' => 'A fictional graphics release.', 'status' => 'published', 'publish_at' => now()->subMinute(), 'created_by' => $admin->id]);
        $onlyGraphics->targetTeams()->sync([Team::where('slug', 'graphics')->value('id')]);
        app(ReleasePublisher::class)->notify($onlyGraphics);
        $this->assertSame(2, $creative->notifications()->count());
        $this->actingAs($creative)->get(route('releases.show', $onlyGraphics))->assertOk();
    }

    public function test_nonprimary_admin_receives_payroll_approval_requests(): void
    {
        $admin = $this->staff('developer', ['admin']);
        $ops = $this->staff('operations', [], ['payroll_authorized' => true]);
        $employeeUser = $this->staff('graphics');
        Employee::create(['user_id' => $employeeUser->id, 'employee_code' => 'TEST-MULTI-001', 'job_title' => 'Designer', 'joined_on' => today(), 'base_salary' => '10000.00']);
        $this->actingAs($ops)->post(route('payroll.store'), ['period' => '2026-01'])->assertRedirect();
        $run = PayrollRun::firstOrFail();
        $this->post(route('payroll.submit', $run))->assertRedirect();
        $this->assertSame(1, $admin->notifications()->count());
        $this->actingAs($admin)->post(route('payroll.approve', $run))->assertRedirect();
        $this->assertSame('approved', $run->fresh()->status);
    }
}
