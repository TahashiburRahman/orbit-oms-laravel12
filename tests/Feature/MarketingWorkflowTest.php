<?php

namespace Tests\Feature;

use App\Models\Brief;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientQuery;
use App\Models\Deliverable;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $team): User
    {
        return User::factory()->create(['team_id' => Team::where('slug', $team)->value('id'), 'active' => true]);
    }

    private function lead(User $marketer, array $extra = []): Lead
    {
        return Lead::create(array_merge(['name' => 'Nusrat Ahmed', 'company' => 'Dhaka Cloud', 'source' => 'Website', 'stage' => 'qualified', 'assigned_to' => $marketer->id, 'created_by' => $marketer->id], $extra));
    }

    private function project(User $owner, string $status = 'active'): Project
    {
        $client = Client::create(['name' => 'Chattogram Commerce', 'status' => 'active', 'created_by' => $owner->id, 'assigned_to' => $owner->id]);

        return Project::create(['client_id' => $client->id, 'name' => 'SaaS onboarding', 'status' => $status, 'team_id' => $owner->team_id, 'assigned_to' => $owner->id, 'created_by' => $owner->id]);
    }

    private function brief(User $marketer, ?User $designer = null): Brief
    {
        return Brief::create(['title' => 'Bangla software launch video', 'type' => 'software_video', 'description' => 'Show onboarding flow in Bangla.', 'requested_by' => $marketer->id, 'assigned_to' => $designer?->id, 'status' => 'open']);
    }

    public function test_qualified_lead_conversion_is_repeatable_without_duplicate_clients(): void
    {
        $marketer = $this->user('marketing');
        $lead = $this->lead($marketer, ['notes' => 'Requested a Bangla onboarding session.']);
        $this->actingAs($marketer)->post(route('leads.convert', $lead))->assertRedirect();
        $clientId = $lead->fresh()->converted_client_id;
        $this->assertNotNull($clientId);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'stage' => 'converted', 'converted_client_id' => $clientId]);
        $this->post(route('leads.convert', $lead))->assertRedirect(route('clients.show', $clientId));
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('clients', ['id' => $clientId, 'notes' => 'Requested a Bangla onboarding session.']);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'source' => 'Website', 'notes' => 'Requested a Bangla onboarding session.']);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => Lead::class, 'subject_id' => $lead->id, 'event' => 'updated']);
        $this->get(route('leads.show', $lead))->assertOk()->assertSee('Open client');
    }

    public function test_unqualified_conversion_and_another_marketers_private_lead_are_blocked(): void
    {
        $marketer = $this->user('marketing');
        $other = $this->user('marketing');
        $lead = $this->lead($marketer, ['stage' => 'new', 'name' => 'Private Dhaka lead']);
        $this->actingAs($marketer)->post(route('leads.convert', $lead))->assertUnprocessable();
        $this->actingAs($other)->get(route('leads.show', $lead))->assertForbidden();
        $this->get(route('leads.index', ['q' => 'Private Dhaka lead']))->assertOk()->assertViewHas('leads', fn ($leads) => $leads->total() === 0);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_graphics_claim_revision_and_approval_keep_private_versions(): void
    {
        Storage::fake('local');
        $marketer = $this->user('marketing');
        $designer = $this->user('graphics');
        $stranger = $this->user('developer');
        $brief = $this->brief($marketer);
        $this->actingAs($designer)->post(route('briefs.claim', $brief))->assertRedirect();
        $this->post(route('briefs.upload', $brief), ['file' => UploadedFile::fake()->create('launch.pdf', 100, 'application/pdf'), 'notes' => 'First storyboard'])->assertRedirect();
        $draft = Deliverable::firstOrFail();
        Storage::disk('local')->assertExists($draft->path);
        $this->post(route('briefs.submit', $brief))->assertRedirect();
        $this->assertSame('submitted', $brief->fresh()->status);
        $this->post(route('briefs.review', $brief), ['decision' => 'approved', 'review_notes' => 'Self approval'])->assertForbidden();
        $this->actingAs($stranger)->get(route('files.download', $draft))->assertForbidden();
        $this->actingAs($marketer)->get(route('files.download', $draft))->assertOk()->assertDownload('launch.pdf');
        $this->post(route('briefs.review', $brief), ['decision' => 'revision_requested', 'review_notes' => 'Use Bangla captions'])->assertRedirect();
        $this->assertDatabaseHas('deliverables', ['id' => $draft->id, 'status' => 'revision_requested', 'review_notes' => 'Use Bangla captions']);
        $this->actingAs($designer)->post(route('briefs.submit', $brief))->assertUnprocessable();
        $this->post(route('briefs.upload', $brief), ['file' => UploadedFile::fake()->create('launch-v2.pdf', 100, 'application/pdf'), 'notes' => 'Bangla captions added'])->assertRedirect();
        $this->post(route('briefs.submit', $brief))->assertRedirect();
        $this->actingAs($marketer)->post(route('briefs.review', $brief), ['decision' => 'approved', 'review_notes' => 'Ready for campaign'])->assertRedirect();
        $this->assertSame('approved', $brief->fresh()->status);
        $this->assertDatabaseCount('deliverables', 2);
        $this->assertDatabaseHas('deliverables', ['brief_id' => $brief->id, 'version' => 2, 'status' => 'approved']);
        $this->get(route('briefs.show', $brief))->assertOk()->assertSee('Use Bangla captions')->assertSee('Ready for campaign');
        $this->assertGreaterThan(0, $marketer->notifications()->count());
    }

    public function test_another_designer_cannot_claim_or_download_assigned_work(): void
    {
        Storage::fake('local');
        $marketer = $this->user('marketing');
        $owner = $this->user('graphics');
        $other = $this->user('graphics');
        $brief = $this->brief($marketer, $owner);
        $this->actingAs($owner)->post(route('briefs.upload', $brief), ['file' => UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf')])->assertRedirect();
        $this->actingAs($other)->get(route('briefs.show', $brief))->assertForbidden();
        $this->post(route('briefs.claim', $brief))->assertForbidden();
        $this->get(route('files.download', Deliverable::firstOrFail()))->assertForbidden();
    }

    public function test_campaign_post_schedule_and_brief_stay_linked(): void
    {
        $marketer = $this->user('marketing');
        $this->actingAs($marketer)->post(route('campaigns.store'), ['name' => 'Dhaka SaaS launch', 'status' => 'planning', 'assigned_to' => $marketer->id, 'starts_at' => '2026-11-01', 'ends_at' => '2026-11-30'])->assertRedirect();
        $campaign = Campaign::firstOrFail();
        $this->post(route('campaigns.posts.store', $campaign), ['title' => 'Launch announcement', 'channel' => 'LinkedIn', 'content' => 'A better day at the office.', 'status' => 'scheduled', 'scheduled_at' => '2026-11-20T10:00'])->assertRedirect();
        $this->post(route('briefs.store'), ['title' => 'Launch graphics', 'type' => 'graphic', 'description' => 'LinkedIn launch creative', 'campaign_id' => $campaign->id])->assertRedirect();
        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Launch announcement')->assertSee('Launch graphics');
        $this->assertDatabaseHas('campaign_posts', ['campaign_id' => $campaign->id, 'status' => 'scheduled']);
        $this->actingAs($this->user('graphics'))->get(route('campaigns.show', $campaign))->assertForbidden();
    }

    public function test_client_query_escalation_preserves_conversation_and_restricts_access(): void
    {
        $marketer = $this->user('marketing');
        $developer = $this->user('developer');
        $other = $this->user('developer');
        $project = $this->project($marketer);
        $this->actingAs($marketer)->post(route('queries.store'), ['project_id' => $project->id, 'subject' => 'Pixel event question', 'description' => 'Client asks about purchase events.', 'status' => 'open', 'priority' => 'high', 'assigned_to' => $marketer->id])->assertRedirect();
        $query = ClientQuery::firstOrFail();
        $this->put(route('queries.update', $query), ['subject' => $query->subject, 'description' => $query->description, 'status' => 'escalated', 'priority' => 'high', 'assigned_to' => $developer->id])->assertRedirect();
        $this->actingAs($developer)->get(route('queries.show', $query))->assertOk();
        $this->post(route('queries.messages.store', $query), ['body' => 'Purchase tracking verified in test mode.'])->assertRedirect();
        $this->actingAs($marketer)->get(route('queries.show', $query))->assertOk()->assertSee('Purchase tracking verified in test mode.');
        $this->actingAs($other)->get(route('queries.show', $query))->assertForbidden();
        $this->post(route('queries.messages.store', $query), ['body' => 'Unauthorized response'])->assertForbidden();
        $this->assertDatabaseCount('query_messages', 1);
    }

    public function test_queries_require_an_accessible_active_project(): void
    {
        $marketer = $this->user('marketing');
        $other = $this->user('developer');
        $inactive = $this->project($marketer, 'completed');
        $private = $this->project($other);
        foreach ([$inactive, $private] as $project) {
            $this->actingAs($marketer)->post(route('queries.store'), ['project_id' => $project->id, 'subject' => 'Not permitted', 'description' => 'Question', 'status' => 'open', 'priority' => 'normal', 'assigned_to' => $marketer->id])->assertSessionHasErrors('project_id');
        }
        $this->assertDatabaseCount('client_queries', 0);
    }

    public function test_marketing_lists_forms_and_edit_screens_render(): void
    {
        $marketer = $this->user('marketing');
        $lead = $this->lead($marketer);
        $project = $this->project($marketer);
        $brief = $this->brief($marketer);
        $campaign = Campaign::create(['name' => 'Office campaign', 'status' => 'planning', 'assigned_to' => $marketer->id, 'created_by' => $marketer->id]);
        $post = $campaign->posts()->create(['title' => 'Office post', 'channel' => 'Facebook', 'content' => 'Launch update', 'status' => 'draft']);
        $query = ClientQuery::create(['project_id' => $project->id, 'subject' => 'Question', 'description' => 'Setup details', 'status' => 'open', 'priority' => 'normal', 'assigned_to' => $marketer->id, 'created_by' => $marketer->id]);
        $this->actingAs($marketer);
        foreach (['leads', 'campaigns', 'briefs', 'queries'] as $module) {
            $this->get(route($module.'.index'))->assertOk();
            $this->get(route($module.'.create'))->assertOk();
        }
        foreach ([['leads', $lead], ['campaigns', $campaign], ['briefs', $brief], ['queries', $query]] as [$module,$record]) {
            $this->get(route($module.'.show',$record))->assertOk();
            $this->get(route($module.'.edit',$record))->assertOk();
        }
        $this->get(route('posts.edit',$post))->assertOk();
    }
}
