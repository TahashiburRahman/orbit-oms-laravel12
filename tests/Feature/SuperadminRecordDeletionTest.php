<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brief;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Deliverable;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuperadminRecordDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function person(bool $superadmin = false): User
    {
        $user = User::factory()->create(['team_id' => Team::where('slug', 'admin')->value('id')]);
        if ($superadmin) {
            $user->forceFill(['is_superadmin' => true])->save();
        }

        return $user;
    }

    private function client(User $user): Client
    {
        return Client::create(['name' => 'Synthetic Client', 'created_by' => $user->id]);
    }

    public function test_ordinary_admin_cannot_delete_or_discover_records_through_management_endpoint(): void
    {
        $admin = $this->person();
        $client = $this->client($admin);
        $this->actingAs($admin)->delete('/manage/clients/'.$client->id)->assertForbidden();
        $this->delete('/manage/clients/999999')->assertForbidden();
        $this->get(route('clients.show', $client))->assertOk()->assertDontSee('Delete client');
        $this->assertModelExists($client);
    }

    public function test_superadmin_deletes_task_and_its_comments_with_an_append_only_audit_record(): void
    {
        $owner = $this->person(true);
        $client = $this->client($owner);
        $task = Task::create(['title' => 'Synthetic task', 'created_by' => $owner->id, 'team_id' => $owner->team_id, 'client_id' => $client->id]);
        $task->comments()->create(['user_id' => $owner->id, 'body' => 'Synthetic discussion']);
        $this->actingAs($owner)->get(route('tasks.show', $task))->assertOk()->assertSee('Delete task');
        $this->delete('/manage/tasks/'.$task->id)->assertRedirect(route('tasks.index'));
        $this->assertModelMissing($task);
        $this->assertDatabaseCount('comments', 0);
        $this->assertModelExists($client);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => Task::class, 'subject_id' => $task->id, 'event' => 'deleted', 'user_id' => $owner->id]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => Task::class, 'subject_id' => $task->id, 'event' => 'created']);
    }

    public function test_parent_deletion_respects_project_task_and_domain_references(): void
    {
        $owner = $this->person(true);
        $client = $this->client($owner);
        $site = $client->websites()->create(['domain' => 'example.test', 'normalized_domain' => 'example.test']);
        $project = Project::create(['name' => 'Synthetic Project', 'client_id' => $client->id, 'team_id' => $owner->team_id, 'created_by' => $owner->id]);
        $task = Task::create(['title' => 'Synthetic task', 'client_id' => $client->id, 'project_id' => $project->id, 'client_website_id' => $site->id, 'team_id' => $owner->team_id, 'created_by' => $owner->id]);
        $this->actingAs($owner)->delete('/manage/clients/'.$client->id)->assertSessionHasErrors('delete');
        $this->delete('/manage/projects/'.$project->id)->assertSessionHasErrors('delete');
        $this->delete('/manage/websites/'.$site->id)->assertSessionHasErrors('delete');
        $this->assertModelExists($client);
        $this->assertModelExists($project);
        $this->assertModelExists($site);
        $this->delete('/manage/tasks/'.$task->id)->assertRedirect();
        $this->delete('/manage/projects/'.$project->id)->assertRedirect();
        $this->delete('/manage/clients/'.$client->id)->assertRedirect();
        $this->assertModelMissing($client);
        $this->assertModelMissing($site);
    }

    public function test_converted_leads_and_their_clients_remain_protected(): void
    {
        $owner = $this->person(true);
        $client = $this->client($owner);
        $lead = Lead::create(['name' => 'Synthetic lead', 'source' => 'Fixture', 'stage' => 'converted', 'created_by' => $owner->id, 'assigned_to' => $owner->id, 'converted_client_id' => $client->id, 'converted_at' => now()]);
        $this->actingAs($owner)->delete('/manage/leads/'.$lead->id)->assertSessionHasErrors('delete');
        $this->delete('/manage/clients/'.$client->id)->assertSessionHasErrors('delete');
        $this->assertModelExists($client);
        $this->assertModelExists($lead);
        $pending = Lead::create(['name' => 'Pending lead', 'source' => 'Fixture', 'created_by' => $owner->id, 'assigned_to' => $owner->id]);
        $this->delete('/manage/leads/'.$pending->id)->assertRedirect();
        $this->assertModelMissing($pending);
    }

    public function test_campaign_parent_requires_posts_and_briefs_to_be_removed_first(): void
    {
        $owner = $this->person(true);
        $campaign = Campaign::create(['name' => 'Synthetic campaign', 'assigned_to' => $owner->id, 'created_by' => $owner->id]);
        $post = $campaign->posts()->create(['title' => 'Synthetic post', 'channel' => 'Fixture', 'content' => 'Synthetic content']);
        $brief = Brief::create(['title' => 'Synthetic brief', 'type' => 'graphic', 'description' => 'Synthetic requirements', 'requested_by' => $owner->id, 'campaign_id' => $campaign->id]);
        $this->actingAs($owner)->delete('/manage/campaigns/'.$campaign->id)->assertSessionHasErrors('delete');
        $this->delete('/manage/posts/'.$post->id)->assertRedirect();
        $this->delete('/manage/briefs/'.$brief->id)->assertRedirect();
        $this->delete('/manage/campaigns/'.$campaign->id)->assertRedirect();
        $this->assertModelMissing($campaign);
    }

    public function test_draft_payroll_can_be_deleted_but_later_states_remain_immutable(): void
    {
        $owner = $this->person(true);
        $employee = Employee::create(['user_id' => $owner->id, 'employee_code' => 'FIXTURE-001', 'job_title' => 'Fixture', 'joined_on' => '2026-01-01', 'base_salary' => '100.00']);
        $this->actingAs($owner);
        foreach (['draft', 'submitted', 'approved', 'paid'] as $index => $status) {
            $run = PayrollRun::create(['period' => '2026-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'status' => 'draft', 'created_by' => $owner->id]);
            $entry = $run->entries()->create(['employee_id' => $employee->id, 'employee_name' => 'Synthetic Employee', 'employee_code' => 'FIXTURE-001', 'base_salary' => '100.00', 'allowances' => '0.00', 'deductions' => '0.00', 'net_pay' => '100.00']);
            $run->update(['status' => $status]);
            $response = $this->delete('/manage/payroll/'.$run->id);
            if ($status === 'draft') {
                $response->assertRedirect(route('payroll.index'));
                $this->assertModelMissing($run);
                $this->assertModelMissing($entry);
                $this->assertDatabaseHas('audit_logs', ['subject_type' => PayrollEntry::class, 'subject_id' => $entry->id, 'event' => 'deleted']);
            } else {
                $response->assertSessionHasErrors('delete');
                $this->assertModelExists($run);
                $this->assertModelExists($entry);
            }
        }
        $this->assertModelExists($employee);
    }

    public function test_unapproved_private_file_is_removed_after_commit_and_approved_files_are_protected(): void
    {
        Storage::fake('local');
        $owner = $this->person(true);
        $brief = Brief::create(['title' => 'Synthetic brief', 'type' => 'graphic', 'description' => 'Synthetic requirements', 'requested_by' => $owner->id, 'status' => 'in_progress']);
        $draft = Deliverable::create(['brief_id' => $brief->id, 'version' => 1, 'uploaded_by' => $owner->id, 'path' => 'briefs/draft.txt', 'original_name' => 'draft.txt', 'mime' => 'text/plain', 'size' => 5, 'status' => 'draft']);
        Storage::disk('local')->put($draft->path, 'draft');
        $this->actingAs($owner)->delete('/manage/deliverables/'.$draft->id)->assertRedirect();
        $this->assertModelMissing($draft);
        Storage::disk('local')->assertMissing($draft->path);
        $approved = Deliverable::create(['brief_id' => $brief->id, 'version' => 2, 'uploaded_by' => $owner->id, 'path' => 'briefs/approved.txt', 'original_name' => 'approved.txt', 'mime' => 'text/plain', 'size' => 8, 'status' => 'approved']);
        $this->delete('/manage/deliverables/'.$approved->id)->assertSessionHasErrors('delete');
        $this->assertModelExists($approved);
    }

    public function test_arbitrary_models_and_audit_records_are_not_deletion_resources(): void
    {
        $owner = $this->person(true);
        $this->actingAs($owner);
        foreach (['audit', 'audit_logs', 'users', 'employees', 'teams', 'payroll_entries', 'payroll_corrections', 'App\\Models\\User'] as $resource) {
            $this->delete('/manage/'.urlencode($resource).'/1')->assertNotFound();
        }
        $this->assertTrue(AuditLog::query()->exists());
        $this->assertModelExists($owner);
    }
}
