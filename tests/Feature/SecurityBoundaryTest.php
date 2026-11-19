<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function staff(string $team): User
    {
        return User::factory()->create(['team_id' => Team::where('slug', $team)->value('id'), 'active' => true]);
    }

    private function project(User $creator, User $owner, string $name): Project
    {
        $client = Client::create(['name' => $name.' Client', 'created_by' => $creator->id, 'assigned_to' => $owner->id, 'status' => 'active']);

        return Project::create(['name' => $name.' Project', 'client_id' => $client->id, 'created_by' => $creator->id, 'assigned_to' => $owner->id, 'team_id' => $owner->team_id, 'status' => 'active']);
    }

    private function task(User $creator, User $owner, Project $project, string $title, string $type = 'general'): Task
    {
        return Task::create(['title' => $title, 'created_by' => $creator->id, 'assigned_to' => $owner->id, 'team_id' => $owner->team_id, 'project_id' => $project->id, 'client_id' => $project->client_id, 'status' => 'open', 'work_type' => $type, 'priority' => 'normal']);
    }

    public function test_search_and_detail_queries_cannot_escape_team_scope(): void
    {
        $admin = $this->staff('admin');
        $developer = $this->staff('developer');
        $marketer = $this->staff('marketing');
        $privateProject = $this->project($admin, $marketer, 'Private marketing');
        $privateTask = $this->task($admin, $marketer, $privateProject, 'Private marketing task');
        $publicProject = $this->project($admin, $developer, 'Developer workspace');
        $this->task($admin, $developer, $publicProject, 'Developer assignment');

        $this->actingAs($developer)->get(route('tasks.index', ['search' => 'Private']))->assertOk()->assertDontSee('Private marketing task');
        $this->get(route('projects.index', ['search' => 'Private']))->assertOk()->assertDontSee('Private marketing Project');
        $this->get(route('clients.index', ['search' => 'Private']))->assertOk()->assertDontSee('Private marketing Client');
        foreach ([route('tasks.show', $privateTask), route('tasks.edit', $privateTask), route('projects.show', $privateProject), route('clients.show', $privateProject->client_id)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('tasks.comments', $privateTask), ['body' => 'Unauthorized comment'])->assertForbidden();
        $this->assertDatabaseMissing('comments', ['body' => 'Unauthorized comment']);
    }

    public function test_project_sharing_does_not_share_another_teams_private_tasks(): void
    {
        $admin = $this->staff('admin');
        $developer = $this->staff('developer');
        $marketer = $this->staff('marketing');
        $project = $this->project($admin, $marketer, 'Shared launch');
        $project->members()->attach($developer);
        $privateTask = $this->task($admin, $marketer, $project, 'Private sales negotiation');
        $this->actingAs($developer)->get(route('projects.show', $project))->assertOk()->assertDontSee('Private sales negotiation');
        $this->get(route('tasks.show', $privateTask))->assertForbidden();
    }

    public function test_developer_cannot_forge_verification_or_change_assignment_fields(): void
    {
        $ops = $this->staff('operations');
        $developer = $this->staff('developer');
        $project = $this->project($ops, $ops, 'Onboarding');
        $task = $this->task($ops, $developer, $project, 'Domain setup', 'domain');
        $this->actingAs($developer)->put(route('tasks.update', $task), [
            'status' => 'in_progress', 'title' => 'Forged title', 'created_by' => $developer->id,
            'assigned_to' => $ops->id, 'team_id' => $ops->team_id, 'work_type' => 'general',
            'verified_by' => $developer->id, 'verified_at' => now()->toDateTimeString(),
            'verification_results' => 'Self-approved',
        ])->assertRedirect();
        $task->refresh();
        $this->assertSame('Domain setup', $task->title);
        $this->assertSame($ops->id, $task->created_by);
        $this->assertSame($developer->id, $task->assigned_to);
        $this->assertSame($developer->team_id, $task->team_id);
        $this->assertSame('domain', $task->work_type);
        $this->assertNull($task->verified_by);
        $this->assertNull($task->verification_results);
        $this->put(route('tasks.update', $task), ['status' => 'verified', 'verification_results' => 'Self-approved'])->assertForbidden();
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_non_admin_cannot_manage_accounts_teams_or_read_company_audit(): void
    {
        $ops = $this->staff('operations');
        $developer = $this->staff('developer');
        $this->actingAs($ops)->get(route('users.index'))->assertForbidden();
        $this->get(route('users.show', $developer))->assertForbidden();
        $this->post(route('users.store'), ['name' => 'Intruder'])->assertForbidden();
        $this->put(route('users.update', $developer), ['active' => false])->assertForbidden();
        $this->put(route('teams.update', $developer->team_id), ['name' => 'Forged team'])->assertForbidden();
        $this->get(route('audit.index'))->assertForbidden();
        $this->assertTrue($developer->fresh()->active);
        $this->assertDatabaseMissing('teams', ['name' => 'Forged team']);
    }

    public function test_existing_development_work_must_keep_a_client_or_project_link(): void
    {
        $ops = $this->staff('operations');
        $developer = $this->staff('developer');
        $project = $this->project($ops, $ops, 'Client launch');
        $task = $this->task($ops, $developer, $project, 'Domain setup', 'domain');
        $this->actingAs($ops)->put(route('tasks.update', $task), [
            'title' => $task->title, 'work_type' => 'domain', 'status' => 'open',
            'priority' => 'normal', 'team_id' => $developer->team_id, 'assigned_to' => $developer->id,
            'client_id' => null, 'project_id' => null,
        ])->assertSessionHasErrors('client_id');
        $this->assertSame($project->id, $task->fresh()->project_id);
    }

    public function test_a_project_explicitly_assigned_to_a_team_is_visible_to_that_team(): void
    {
        $admin = $this->staff('admin');
        $developer = $this->staff('developer');
        $project = $this->project($admin, $admin, 'Team queue');
        $project->update(['team_id' => $developer->team_id, 'assigned_to' => null]);
        $this->actingAs($developer)->get(route('projects.show', $project))->assertOk()->assertSee('Team queue Project');
    }
}
