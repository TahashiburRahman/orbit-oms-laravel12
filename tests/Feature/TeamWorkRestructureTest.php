<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeamWorkRestructureTest extends TestCase
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

    private function devData(array $extra = []): array
    {
        return array_replace(['work_date' => '2026-11-19', 'work_time' => '10:25', 'domain_name' => 'https://example.test', 'description' => 'Resolve the customer issue', 'classification' => 'Support', 'actions' => '', 'work_status' => 'pending'], $extra);
    }

    public function test_developer_form_defaults_and_server_identity_with_optional_website_and_actions(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 10:25:00', 'Asia/Dhaka'));
        $dev = $this->person('developer');
        $this->actingAs($dev)->get(route('work.create', 'developer'))->assertOk()->assertSee('value="2026-11-19"', false)->assertSee('value="10:25"', false)->assertSee($dev->name);
        $this->post(route('work.store', 'developer'), $this->devData())->assertRedirect();
        $task = Task::firstOrFail();
        $this->assertSame($dev->id, $task->reported_by);
        $this->assertSame($dev->id, $task->assigned_to);
        $this->assertSame('2026-11-19 10:25', $task->reported_at->format('Y-m-d H:i'));
        $this->assertSame('developer', $task->record_type);
        $this->assertSame('open', $task->status);
        $this->assertNull($task->website_name);
        $this->assertNull($task->actions);
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('Support')->assertSee('Pending');
        $this->get(route('work.edit', $task))->assertOk();
        $this->travelBack();
    }

    public function test_type_specific_validation_rejects_impersonation_invalid_dates_domains_classifications_and_statuses(): void
    {
        $dev = $this->person('developer');
        $this->actingAs($dev);
        foreach ([['classification' => 'Other'], ['work_status' => 'deal_approved'], ['domain_name' => 'javascript:alert(1)'], ['work_date' => '2026-02-31'], ['work_time' => '25:99'], ['reported_by' => 123], ['name' => 'Someone else'], ['client_name' => ['Invalid']], ['status' => 'verified']] as $bad) {
            $this->post(route('work.store', 'developer'), $this->devData($bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_operations_fields_optional_client_and_invalid_cross_team_fields(): void
    {
        $ops = $this->person('operations');
        $this->actingAs($ops)->get(route('work.create', 'operations'))->assertOk()->assertSee('Client name (optional)')->assertDontSee('name="classification"', false)->assertDontSee('name="work_date"', false);
        $data = ['domain_name' => 'example.test', 'description' => 'Prepare client onboarding', 'actions' => '', 'work_status' => 'pending'];
        $this->post(route('work.store', 'operations'), $data)->assertRedirect();
        $task = Task::firstOrFail();
        $this->assertNull($task->client_name);
        $this->assertSame('operations', $task->record_type);
        $this->post(route('work.store', 'operations'), $data + ['classification' => 'Pixel'])->assertSessionHasErrors('classification');
        $this->post(route('work.store', 'operations'), array_replace($data, ['domain_name' => '']))->assertSessionHasErrors('domain_name');
        $this->put(route('work.update', $task), array_replace($data, ['work_status' => 'completed', 'actions' => 'Onboarding finished']))->assertRedirect();
        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_membership_and_assignment_apply_including_admin_with_additional_membership(): void
    {
        $dev = $this->person('developer');
        $other = $this->person('developer');
        $graphics = $this->person('graphics');
        $admin = $this->person('admin');
        $this->actingAs($graphics)->get(route('work.create', 'developer'))->assertForbidden();
        $this->post(route('work.store', 'developer'), $this->devData())->assertForbidden();
        $this->actingAs($admin)->get(route('work.create', 'developer'))->assertForbidden();
        $admin->teams()->attach($dev->team_id);
        $this->get(route('work.create', 'developer'))->assertOk();
        $this->actingAs($dev)->post(route('work.store', 'developer'), $this->devData())->assertRedirect();
        $task = Task::firstOrFail();
        $task->update(['work_type' => 'pixel']);
        $this->actingAs($other)->put(route('work.update', $task), $this->devData())->assertForbidden();
        $this->actingAs($graphics)->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_technical_completed_means_submitted_until_operations_verifies_and_no_direct_bypass(): void
    {
        $ops = $this->person('operations');
        $dev = $this->person('developer');
        $client = Client::create(['name' => 'Fixture Client', 'created_by' => $ops->id, 'assigned_to' => $ops->id]);
        $task = Task::create(['title' => 'Pixel setup', 'description' => 'Install pixel', 'client_id' => $client->id, 'created_by' => $ops->id, 'assigned_to' => $dev->id, 'team_id' => $dev->team_id, 'work_type' => 'pixel', 'status' => 'in_progress', 'record_type' => 'developer','configuration_summary'=>'Original configuration','handover_notes'=>'Original handover']);
        $this->actingAs($dev)->put(route('work.update', $task), $this->devData(['work_status' => 'completed', 'classification' => 'Pixel', 'actions' => 'Configured pixel and verified events']))->assertRedirect();
        $this->assertSame('submitted', $task->fresh()->status);
        $this->assertSame('Original configuration',$task->fresh()->configuration_summary);
        $this->assertSame('Original handover',$task->fresh()->handover_notes);
        $this->assertSame('in_progress', $task->fresh()->work_status);
        $this->put(route('tasks.update', $task), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->put(route('work.update', $task), $this->devData(['work_status' => 'verified']))->assertSessionHasErrors('work_status');
        $this->actingAs($ops)->put(route('tasks.update', $task), ['status' => 'verified', 'verification_results' => 'Events verified by Operations'])->assertRedirect();
        $this->assertSame('verified', $task->fresh()->status);
        $this->assertSame('completed', $task->fresh()->work_status);
        $this->assertSame($ops->id, $task->fresh()->verified_by);
    }

    public function test_required_structured_fields_cannot_be_bypassed_via_old_task_update_endpoint(): void
    {
        $dev = $this->person('developer');
        $this->actingAs($dev)->post(route('work.store', 'developer'), $this->devData())->assertRedirect();
        $task = Task::firstOrFail();
        $this->put(route('tasks.update', $task), ['status' => 'completed'])->assertSessionHasErrors();
        $this->assertSame('open', $task->fresh()->status);
        $this->get(route('tasks.index', ['classification' => 'Support', 'record_type' => 'developer']))->assertOk()->assertSee($task->title);
        $this->get(route('tasks.index', ['classification' => 'Courier']))->assertOk()->assertDontSee($task->title);
    }

    public function test_legacy_missing_fields_remain_usable_and_links_comments_history_survive(): void
    {
        $dev = $this->person('developer');
        $client = Client::create(['name' => 'Legacy Client', 'created_by' => $dev->id]);
        $project = Project::create(['name' => 'Legacy Project', 'client_id' => $client->id, 'team_id' => $dev->team_id, 'created_by' => $dev->id]);
        $task = Task::create(['title' => 'Legacy work', 'description' => 'Original details', 'client_id' => $client->id, 'project_id' => $project->id, 'created_by' => $dev->id, 'assigned_to' => $dev->id, 'team_id' => $dev->team_id, 'work_type' => 'general', 'status' => 'open', 'record_type' => 'developer']);
        $task->comments()->create(['body' => 'Preserve conversation', 'user_id' => $dev->id]);
        $this->actingAs($dev)->put(route('work.update', $task), ['work_status' => 'in_progress', 'actions' => 'Started'])->assertRedirect();
        $task->refresh();
        $this->assertSame($client->id, $task->client_id);
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame('Original details', $task->description);
        $this->assertNull($task->domain_name);
        $this->assertFalse($task->structured_fields_required);
        $this->assertSame(1, $task->comments()->count());
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('Preserve conversation')->assertSee('Started');
    }

    public function test_every_team_can_refer_without_creating_an_operations_task_or_a_client(): void
    {
        foreach (['admin', 'operations', 'developer', 'marketing', 'graphics'] as $slug) {
            $u = $this->person($slug);
            $this->actingAs($u)->get(route('referral.create'))->assertOk();
            $this->post(route('referral.store'), ['name' => 'Prospect '.$slug, 'domain_name' => '', 'deal_status' => 'in_progress'])->assertRedirect();
            $lead = Lead::latest('id')->firstOrFail();
            $this->assertSame('referral', $lead->record_type);
            $this->assertSame($u->id, $lead->submitted_by);
            $this->get(route('referral.index'))->assertOk()->assertSee($lead->name);
            $this->get(route('leads.show', $lead))->assertOk();
        }
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('leads', 5);
    }

    public function test_referrer_corrections_and_deal_decisions_are_separately_authorized(): void
    {
        $ops = $this->person('operations');
        $other = $this->person('graphics');
        $admin = $this->person('admin');
        $this->actingAs($ops)->post(route('referral.store'), ['name' => 'Prospect', 'deal_status' => 'in_progress'])->assertRedirect();
        $lead = Lead::firstOrFail();
        $this->put(route('deals.update', $lead), ['name' => 'Corrected prospect', 'domain_name' => 'example.test', 'deal_status' => 'in_progress'])->assertRedirect();
        $this->put(route('deals.update', $lead), ['name' => 'Corrected prospect', 'deal_status' => 'deal_approved'])->assertForbidden();
        $this->post(route('referral.store'), ['name' => 'Unapproved', 'deal_status' => 'deal_approved'])->assertForbidden();
        $this->actingAs($other)->get(route('leads.show', $lead))->assertForbidden();
        $this->get(route('deals.edit', $lead))->assertForbidden();
        $this->get(route('referral.index', ['q' => 'Corrected']))->assertOk()->assertDontSee('Corrected prospect');
        $this->actingAs($admin)->put(route('deals.update', $lead), ['name' => 'Approved prospect', 'deal_status' => 'deal_approved'])->assertRedirect();
        $this->assertDatabaseCount('clients', 0);
        $this->assertSame('new', $lead->fresh()->stage);
        $this->post(route('leads.convert', $lead))->assertStatus(422);
        $this->put(route('leads.update', $lead), ['name' => 'Approved prospect', 'source' => 'Employee referral', 'stage' => 'qualified', 'assigned_to' => $admin->id])->assertRedirect();
        $this->post(route('leads.convert', $lead))->assertRedirect();
        $this->post(route('leads.convert', $lead))->assertRedirect();
        $this->assertDatabaseCount('clients', 1);
        $this->actingAs($ops)->put(route('deals.update', $lead), ['name' => 'Changed again', 'deal_status' => 'in_progress'])->assertStatus(422);
    }

    public function test_marketing_routine_is_distinct_from_referrals_and_existing_lead_scope(): void
    {
        $marketing = $this->person('marketing');
        $other = $this->person('marketing');
        $ops = $this->person('operations');
        $this->actingAs($ops)->get(route('marketing_update.create'))->assertForbidden();
        $this->actingAs($marketing)->get(route('marketing_update.create'))->assertOk()->assertSee('Client name')->assertSee('Deal Approved')->assertDontSee('name="actions"', false);
        $this->post(route('marketing_update.store'), ['name' => 'Routine prospect', 'domain_name' => 'https://example.test/landing', 'deal_status' => 'deal_approved'])->assertRedirect();
        $lead = Lead::firstOrFail();
        $this->assertSame('marketing_update', $lead->record_type);
        $this->assertSame('new', $lead->stage);
        $this->assertDatabaseCount('clients', 0);
        $this->get(route('marketing_update.index', ['deal_status' => 'deal_approved']))->assertOk()->assertSee('Routine prospect');
        $this->get(route('referral.index'))->assertOk()->assertDontSee('Routine prospect');
        $this->actingAs($other)->get(route('marketing_update.index'))->assertOk()->assertDontSee('Routine prospect');
        $this->actingAs($marketing)->post(route('marketing_update.store'), ['name' => 'Invalid', 'deal_status' => 'completed', 'actions' => 'Wrong fields'])->assertSessionHasErrors();
    }

    public function test_admin_can_reassign_structured_work_without_overwriting_reporter_or_details(): void
    {
        $dev = $this->person('developer');
        $next = $this->person('developer');
        $ops = $this->person('operations');
        $admin = $this->person('admin');
        $this->actingAs($dev)->post(route('work.store', 'developer'), $this->devData())->assertRedirect();
        $task = Task::firstOrFail();
        $this->put(route('tasks.assign', $task), ['assigned_to' => $next->id])->assertForbidden();
        $this->actingAs($admin)->put(route('tasks.assign', $task), ['assigned_to' => $ops->id])->assertSessionHasErrors('assigned_to');
        $this->put(route('tasks.assign', $task), ['assigned_to' => $next->id])->assertRedirect();
        $this->assertSame($next->id, $task->fresh()->assigned_to);
        $this->assertSame($dev->id, $task->fresh()->reported_by);
        $this->assertSame('Resolve the customer issue', $task->fresh()->description);
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('Manage assignment');
    }

    public function test_pending_referral_defaults_and_authorized_marketing_assignment_scope(): void
    {
        $ops = $this->person('operations');
        $marketing = $this->person('marketing');
        $admin = $this->person('admin');
        $this->actingAs($ops)->post(route('referral.store'), ['name' => 'A prospect'])->assertRedirect();
        $lead = Lead::firstOrFail();
        $this->assertSame('in_progress', $lead->deal_status);
        $this->actingAs($marketing)->get(route('referral.index'))->assertOk()->assertDontSee('A prospect');
        $this->actingAs($admin)->put(route('leads.update', $lead), ['name' => 'A prospect', 'source' => 'Employee referral', 'stage' => 'new', 'assigned_to' => $marketing->id])->assertRedirect();
        $this->actingAs($marketing)->get(route('referral.index'))->assertOk()->assertSee('A prospect');
        $this->put(route('deals.update', $lead), ['name' => 'A prospect', 'deal_status' => 'declined'])->assertRedirect();
        $this->actingAs($ops)->get(route('deals.edit', $lead))->assertForbidden();
        $this->get(route('leads.show', $lead))->assertOk()->assertSee('Declined');
    }

    public function test_completed_requires_actions_and_multi_team_forms_use_actual_memberships(): void
    {
        $ops = $this->person('operations');
        $devTeam = Team::where('slug', 'developer')->value('id');
        $ops->teams()->attach($devTeam);
        $this->actingAs($ops)->get(route('dashboard.team', 'developer'))->assertOk()->assertSee('Add Developer update');
        $this->get(route('dashboard.team', 'operations'))->assertOk()->assertSee('Add Operations update');
        $this->post(route('work.store', 'developer'), $this->devData(['work_status' => 'completed']))->assertSessionHasErrors('actions');
        $this->post(route('work.store', 'developer'), $this->devData(['work_status' => 'completed', 'actions' => 'Resolved']))->assertRedirect();
        $this->assertSame('completed', Task::firstOrFail()->status);
    }

    public function test_unmapped_legacy_status_is_readable_and_preserved_when_only_notes_change(): void
    {
        $ops = $this->person('operations');
        $task = Task::create(['title' => 'Legacy external hold', 'description' => 'Old requirements', 'created_by' => $ops->id, 'assigned_to' => $ops->id, 'team_id' => $ops->team_id, 'record_type' => 'operations', 'work_type' => 'general', 'status' => 'external_hold']);
        $this->actingAs($ops)->get(route('work.edit', $task))->assertOk()->assertSee('Legacy: external_hold');
        $this->put(route('work.update', $task), ['work_status' => 'external_hold', 'actions' => 'Awaiting response'])->assertRedirect();
        $this->assertSame('external_hold', $task->fresh()->status);
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('external_hold');
    }
}
