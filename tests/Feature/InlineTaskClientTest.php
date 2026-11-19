<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InlineTaskClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function person(string $team): User
    {
        return User::factory()->create(['team_id' => Team::where('slug', $team)->value('id')]);
    }

    private function taskData(User $u, array $extra = []): array
    {
        $data = ['title' => 'Client website support', 'status' => 'open', 'priority' => 'normal', 'client_name' => 'Fixture Client', 'domain_name' => 'https://shop.example.test', 'website_name' => 'Online shop'];
        if ($u->inTeam('admin', 'operations')) {
            $data += ['team_id' => $u->team_id, 'assigned_to' => $u->id, 'work_type' => 'general'];
        }

        return array_replace($data, $extra);
    }

    public function test_all_five_teams_create_clients_inline_and_separately(): void
    {
        foreach (['admin', 'operations', 'developer', 'marketing', 'graphics'] as $slug) {
            $u = $this->person($slug);
            $this->actingAs($u)->get(route('tasks.create'))->assertOk()->assertSee('data-client-entry', false)->assertDontSee('name="client_id" label="Client"', false);
            $this->post(route('tasks.store'), $this->taskData($u, ['client_name' => 'Inline '.$slug]))->assertRedirect()->assertSessionHasNoErrors();
            $client = Client::where('name', 'Inline '.$slug)->firstOrFail();
            $task = Task::where('client_id', $client->id)->firstOrFail();
            $this->assertSame($u->id, $client->created_by);
            $this->assertSame($u->id, $task->assigned_to);
            $this->assertSame($u->team_id, $task->team_id);
            $this->assertSame('shop.example.test', $client->websites()->firstOrFail()->normalized_domain);
            $this->assertNotNull($task->client_website_id);
            $this->get(route('clients.show', $client))->assertOk()->assertSee('Online shop');
            $this->get(route('clients.create'))->assertOk();
            $this->post(route('clients.store'), ['name' => 'Separate '.$slug, 'status' => 'active', 'domain_name' => 'separate.example.test'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('clients', ['name' => 'Separate '.$slug, 'created_by' => $u->id]);
        }
        $this->assertDatabaseCount('clients', 10);
        $this->assertDatabaseCount('client_websites', 10);
    }

    public function test_existing_client_suggestions_and_multiple_domains_reuse_records(): void
    {
        $u = $this->person('developer');
        $this->actingAs($u)->post(route('tasks.store'), $this->taskData($u))->assertRedirect();
        $client = Client::firstOrFail();
        $site = $client->websites()->firstOrFail();
        $this->getJson(route('clients.suggestions', ['q' => 'Fixture']))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('0.id', $client->id)->assertJsonPath('0.websites.0.domain', $site->domain)->assertJsonMissingPath('0.email')->assertJsonMissingPath('0.notes');
        $this->post(route('tasks.store'), $this->taskData($u, ['client_name' => ' fixture client ', 'domain_name' => 'https://SHOP.example.test/other-page']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('client_websites', 1);
        $this->post(route('tasks.store'), $this->taskData($u, ['client_id' => $client->id, 'domain_name' => 'portal.example.test', 'website_name' => 'Customer portal']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('client_websites', 2);
        $this->post(route('tasks.store'), $this->taskData($u, ['client_id' => $client->id, 'client_website_id' => $site->id, 'domain_name' => null, 'website_name' => null]))->assertRedirect()->assertSessionHasNoErrors();
        $task = Task::latest('id')->firstOrFail();
        $this->assertSame($site->id, $task->client_website_id);
        $this->assertSame($site->domain, $task->domain_name);
        $this->assertSame('Online shop', $task->website_name);
    }

    public function test_suggestions_and_submitted_ids_enforce_actual_client_scope(): void
    {
        $owner = $this->person('operations');
        $outsider = $this->person('graphics');
        $client = Client::create(['name' => 'Private Customer', 'created_by' => $owner->id]);
        $site = $client->websites()->create(['domain' => 'private.example.test', 'normalized_domain' => 'private.example.test']);
        $this->actingAs($outsider)->getJson(route('clients.suggestions', ['q' => 'Private']))->assertExactJson([]);
        $this->getJson(route('clients.suggestions', ['id' => $client->id]))->assertExactJson([]);
        $this->post(route('tasks.store'), $this->taskData($outsider, ['client_id' => $client->id, 'client_name' => $client->name]))->assertForbidden();
        $this->post(route('tasks.store'), $this->taskData($outsider, ['client_website_id' => $site->id]))->assertSessionHasErrors('client_website_id');
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('tasks', 0);
        // A task assignment explicitly shares the linked client context.
        Task::create(['title' => 'Shared task', 'client_id' => $client->id, 'team_id' => $outsider->team_id, 'assigned_to' => $outsider->id, 'created_by' => $owner->id]);
        $this->getJson(route('clients.suggestions', ['q' => 'Private']))->assertJsonPath('0.id', $client->id);
        $this->get(route('clients.edit', $client))->assertForbidden();
    }

    public function test_website_must_belong_to_selected_client_and_task_failures_roll_back_inline_data(): void
    {
        $u = $this->person('operations');
        $other = Client::create(['name' => 'Other Customer', 'created_by' => $u->id]);
        $site = $other->websites()->create(['domain' => 'other.example.test', 'normalized_domain' => 'other.example.test']);
        $this->actingAs($u)->post(route('tasks.store'), $this->taskData($u, ['client_website_id' => $site->id]))->assertSessionHasErrors('client_website_id');
        $this->post(route('tasks.store'), $this->taskData($u, ['status' => 'blocked']))->assertSessionHasErrors('blocker');
        $this->post(route('tasks.store'), $this->taskData($u, ['client_id' => $other->id]))->assertSessionHasErrors('client_name');
        $project = Project::create(['name' => 'Other project', 'client_id' => $other->id, 'team_id' => $u->team_id, 'created_by' => $u->id]);
        $this->post(route('tasks.store'), $this->taskData($u, ['project_id' => $project->id]))->assertSessionHasErrors('project_id');
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('client_websites', 1);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_non_managers_cannot_assign_other_teams_or_create_technical_requests(): void
    {
        $u = $this->person('graphics');
        $other = $this->person('developer');
        $this->actingAs($u)->get(route('tasks.create', ['type' => 'pixel']))->assertForbidden();
        foreach ([['team_id' => $other->team_id], ['assigned_to' => $other->id], ['work_type' => 'pixel'], ['status' => 'verified']] as $extra) {
            $this->post(route('tasks.store'), $this->taskData($u, $extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_developer_and_operations_routine_forms_link_new_and_existing_client_domains(): void
    {
        foreach (['developer', 'operations'] as $slug) {
            $u = $this->person($slug);
            $data = ['client_name' => 'Routine '.$slug, 'domain_name' => 'routine.example.test', 'website_name' => 'Routine website', 'description' => 'Handle support', 'work_status' => 'pending'];
            if ($slug === 'developer') $data += ['work_date' => '2026-11-19', 'work_time' => '12:30', 'classification' => 'Support'];
            $this->actingAs($u)->post(route('work.store', $slug), $data)->assertRedirect()->assertSessionHasNoErrors();
            $client = Client::where('name', 'Routine '.$slug)->firstOrFail();
            $site = $client->websites()->firstOrFail();
            $this->post(route('work.store', $slug), array_replace($data, ['client_id' => $client->id, 'client_website_id' => $site->id, 'domain_name' => null]))->assertRedirect()->assertSessionHasNoErrors();
            $task = Task::latest('id')->firstOrFail();
            $this->assertSame($client->id, $task->client_id);
            $this->assertSame($site->id, $task->client_website_id);
            $this->get(route('work.edit', $task))->assertOk()->assertSee('routine.example.test')->assertSee('saved-client-domain');
        }
        $this->assertDatabaseCount('clients', 2);
        $this->assertDatabaseCount('tasks', 4);
    }

    public function test_ambiguous_existing_names_require_an_explicit_selection_and_wildcards_are_literal(): void
    {
        $u = $this->person('operations');
        $a = Client::create(['name' => 'Same Name', 'created_by' => $u->id]);
        Client::create(['name' => 'Same Name', 'created_by' => $u->id]);
        $this->actingAs($u)->post(route('tasks.store'), $this->taskData($u, ['client_name' => 'Same Name']))->assertSessionHasErrors('client_name');
        $this->post(route('tasks.store'), $this->taskData($u, ['client_name' => 'Same Name', 'client_id' => $a->id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->getJson(route('clients.suggestions', ['q' => '%']))->assertExactJson([]);
        $this->getJson(route('clients.suggestions', ['q' => '_']))->assertExactJson([]);
        $this->getJson(route('clients.suggestions'))->assertExactJson([]);
    }

    public function test_project_only_input_links_its_client_and_domain_and_updates_cannot_break_the_link(): void
    {
        $u = $this->person('operations');
        $client = Client::create(['name' => 'Project Customer', 'created_by' => $u->id]);
        $project = Project::create(['name' => 'Project', 'client_id' => $client->id, 'team_id' => $u->team_id, 'created_by' => $u->id]);
        $this->actingAs($u)->post(route('tasks.store'), $this->taskData($u, ['client_name' => '', 'project_id' => $project->id]))->assertRedirect()->assertSessionHasNoErrors();
        $task = Task::firstOrFail();
        $this->assertSame($client->id, $task->client_id);
        $this->assertNotNull($task->client_website_id);
        $this->put(route('tasks.update', $task), ['status' => 'open', 'client_name' => 'Wrong client', 'domain_name' => 'new.example.test'])->assertSessionHasErrors('project_id');
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('client_websites', 1);
        $this->assertSame($client->id, $task->fresh()->client_id);
    }

    public function test_editing_keeps_historical_domain_text_and_partial_routine_updates_keep_client_links(): void
    {
        $u = $this->person('operations');
        $this->actingAs($u)->post(route('tasks.store'), $this->taskData($u))->assertRedirect();
        $task = Task::firstOrFail();
        $task->update(['domain_name' => 'https://SHOP.example.test/old-page', 'website_name' => 'Historical label']);
        $this->put(route('tasks.update', $task), ['status' => 'open', 'client_id' => $task->client_id, 'client_name' => $task->client_name, 'client_website_id' => $task->client_website_id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('https://SHOP.example.test/old-page', $task->fresh()->domain_name);
        $this->assertSame('Historical label', $task->fresh()->website_name);
        $task->update(['record_type' => 'operations', 'structured_fields_required' => true]);
        $this->put(route('work.update', $task), ['work_status' => 'pending', 'domain_name' => 'another.example.test', 'description' => 'Follow up'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($task->client_id, $task->fresh()->client_id);
        $this->assertSame('another.example.test', $task->fresh()->clientWebsite->domain);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('client_websites', 2);
    }
}
