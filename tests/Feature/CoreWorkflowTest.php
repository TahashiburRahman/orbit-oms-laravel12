<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $team): User
    {
        return User::factory()->create(['team_id' => Team::where('slug', $team)->value('id')]);
    }

    private function client(User $owner): Client
    {
        return Client::create(['name' => 'Ayesha Karim', 'company' => 'Dhaka Commerce', 'email' => 'ayesha@example.test', 'status' => 'active', 'created_by' => $owner->id, 'assigned_to' => $owner->id]);
    }

    public function test_operations_developer_handover_requires_operations_verification(): void
    {
        $ops = $this->person('operations');
        $dev = $this->person('developer');
        $client = $this->client($ops);
        $data = ['title' => 'Connect client domain', 'client_id' => $client->id, 'team_id' => $dev->team_id, 'assigned_to' => $dev->id, 'work_type' => 'domain', 'priority' => 'high', 'status' => 'open', 'due_date' => today()->addDays(2)->toDateString()];
        $this->actingAs($ops)->post('/tasks', $data)->assertRedirect();
        $task = Task::firstOrFail();
        $this->actingAs($dev)->get('/tasks/'.$task->id)->assertOk();
        $this->put('/tasks/'.$task->id, ['status' => 'verified', 'verification_results' => 'Looks okay'])->assertForbidden();
        $this->put('/tasks/'.$task->id, ['status' => 'blocked', 'blocker' => 'Waiting for DNS access'])->assertRedirect();
        $this->put('/tasks/'.$task->id, ['status' => 'submitted'])->assertSessionHasErrors('handover_notes');
        $this->put('/tasks/'.$task->id, ['status' => 'submitted', 'configuration_summary' => 'CNAME recorded; no secrets retained.', 'handover_notes' => 'DNS resolves. Verify SSL.'])->assertRedirect();
        $this->actingAs($ops)->put('/tasks/'.$task->id, [...$data, 'status' => 'verified', 'verification_results' => 'DNS and TLS checks passed.'])->assertRedirect();
        $this->assertSame('verified', $task->fresh()->status);
        $this->assertSame($ops->id, $task->fresh()->verified_by);
        $this->get('/tasks/'.$task->id)->assertOk()->assertSee('DNS and TLS checks passed.');
        $this->assertDatabaseHas('audit_logs', ['subject_type' => Task::class, 'subject_id' => $task->id, 'event' => 'updated']);
        $this->assertDatabaseCount('notifications', 7);
    }

    public function test_lists_search_details_comments_and_updates_hide_other_teams_private_work(): void
    {
        $ops = $this->person('operations');
        $dev = $this->person('developer');
        $marketer = $this->person('marketing');
        $client = $this->client($ops);
        $task = Task::create(['title' => 'Confidential engineering task', 'client_id' => $client->id, 'team_id' => $dev->team_id, 'assigned_to' => $dev->id, 'created_by' => $ops->id, 'work_type' => 'general', 'status' => 'open']);
        $this->actingAs($marketer)->get('/tasks?search=Confidential')->assertOk()->assertDontSee($task->title);
        $this->get('/tasks/'.$task->id)->assertForbidden();
        $this->get('/tasks/'.$task->id.'/edit')->assertForbidden();
        $this->put('/tasks/'.$task->id, ['status' => 'completed'])->assertForbidden();
        $this->post('/tasks/'.$task->id.'/comments', ['body' => 'Not allowed'])->assertForbidden();
        $this->get('/clients/'.$client->id)->assertForbidden();
        $this->get('/clients?search=Dhaka')->assertOk()->assertDontSee('Dhaka Commerce');
    }

    public function test_project_membership_explicitly_shares_project_and_client(): void
    {
        $ops = $this->person('operations');
        $marketing = $this->person('marketing');
        $graphics = $this->person('graphics');
        $client = $this->client($ops);
        $this->actingAs($ops)->post('/projects', ['name' => 'Commerce onboarding', 'client_id' => $client->id, 'team_id' => $ops->team_id, 'assigned_to' => $ops->id, 'members' => [$marketing->id], 'status' => 'active', 'due_date' => today()->addWeek()->toDateString()])->assertRedirect();
        $project = Project::firstOrFail();
        $this->actingAs($marketing)->get('/projects/'.$project->id)->assertOk();
        $this->get('/clients/'.$client->id)->assertOk();
        $this->get('/projects/'.$project->id.'/edit')->assertForbidden();
        $this->actingAs($graphics)->get('/projects/'.$project->id)->assertForbidden();
        $this->actingAs($ops)->get('/projects/'.$project->id.'/edit')->assertOk()->assertSee('multiple', false);
    }

    public function test_client_crud_filters_and_task_comment_work(): void
    {
        $ops = $this->person('operations');
        $this->actingAs($ops)->get('/clients/create')->assertOk();
        $this->post('/clients', ['name' => 'Shahid Hasan', 'company' => 'Chattogram SaaS', 'status' => 'active', 'assigned_to' => $ops->id])->assertRedirect();
        $client = Client::firstOrFail();
        $this->put('/clients/'.$client->id, ['name' => 'Shahid Hasan', 'company' => 'Chattogram SaaS', 'status' => 'inactive', 'assigned_to' => $ops->id])->assertRedirect();
        $this->get('/clients?status=inactive')->assertOk()->assertSee('Chattogram SaaS');
        $this->post('/tasks', ['title' => 'Onboarding checklist', 'team_id' => $ops->team_id, 'assigned_to' => $ops->id, 'work_type' => 'general', 'priority' => 'normal', 'status' => 'open'])->assertRedirect();
        $task = Task::firstOrFail();
        $this->post('/tasks/'.$task->id.'/comments', ['body' => 'Welcome call scheduled.'])->assertRedirect();
        $this->get('/tasks/'.$task->id)->assertOk()->assertSee('Welcome call scheduled.');
        $this->put('/tasks/'.$task->id, ['title' => $task->title, 'team_id' => $ops->team_id, 'assigned_to' => $ops->id, 'work_type' => 'general', 'priority' => 'normal', 'status' => 'completed'])->assertRedirect();
    }
}
