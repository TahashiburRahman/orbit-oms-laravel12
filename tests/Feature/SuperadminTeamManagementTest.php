<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\ReleaseNote;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function person(string $slug = 'admin', bool $superadmin = false): User
    {
        return User::factory()->create(['team_id' => Team::where('slug', $slug)->value('id'), 'is_superadmin' => $superadmin]);
    }

    public function test_superadmin_can_create_rename_and_delete_an_unlinked_team_with_audit(): void
    {
        $owner = $this->person(superadmin: true);
        $this->actingAs($owner)->get(route('teams.index'))->assertOk()->assertSee('Create team')->assertSee('Delete team');
        $this->post(route('teams.store'), ['name' => 'Customer success', 'slug' => 'customer-success'])->assertRedirect(route('teams.index'))->assertSessionHasNoErrors();
        $team = Team::where('slug', 'customer-success')->firstOrFail();
        $this->put(route('teams.update', $team), ['name' => 'Customer care'])->assertRedirect(route('teams.index'))->assertSessionHasNoErrors();
        $this->assertSame('customer-success', $team->fresh()->slug);
        $this->assertSame('Customer care', $team->fresh()->name);
        $this->delete(route('teams.destroy', $team))->assertRedirect(route('teams.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        foreach (['team_created', 'team_renamed', 'team_deleted'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['subject_type' => Team::class, 'subject_id' => $team->id, 'event' => $event, 'user_id' => $owner->id]);
        }
    }

    public function test_ordinary_admin_can_rename_but_cannot_create_or_delete_teams(): void
    {
        $admin = $this->person();
        $team = Team::where('slug', 'marketing')->firstOrFail();
        $this->actingAs($admin)->get(route('teams.index'))->assertOk()->assertDontSee('Create team')->assertDontSee('Delete team');
        $this->put(route('teams.update', $team), ['name' => 'Growth'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('teams.store'), ['name' => 'Forbidden', 'slug' => 'forbidden'])->assertForbidden();
        $this->delete(route('teams.destroy', $team))->assertForbidden();
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Growth', 'slug' => 'marketing']);
        $employee = $this->person('developer');
        $this->actingAs($employee)->get(route('teams.index'))->assertForbidden();
        $this->put(route('teams.update', $team), ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_names_and_unique_stable_workflow_keys_are_validated(): void
    {
        $this->actingAs($this->person(superadmin: true));
        foreach (['Admin', 'contains space', '1starts-with-number', '../unsafe', 'admin'] as $slug) {
            $this->post(route('teams.store'), ['name' => 'Fixture', 'slug' => $slug])->assertSessionHasErrors('slug');
        }
        $team = Team::where('slug', 'marketing')->firstOrFail();
        $this->put(route('teams.update', $team), ['name' => 'Growth', 'slug' => 'growth'])->assertSessionHasErrors('slug');
        $this->post(route('teams.store'), ['name' => '', 'slug' => 'valid'])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('teams', 5);
    }

    public function test_primary_and_secondary_memberships_prevent_team_deletion(): void
    {
        $owner = $this->person(superadmin: true);
        $team = Team::create(['name' => 'Customer care', 'slug' => 'customer-care']);
        $member = User::factory()->create(['team_id' => $team->id]);
        $this->actingAs($owner)->delete(route('teams.destroy', $team))->assertSessionHasErrors('team');
        $member->update(['team_id' => Team::where('slug', 'developer')->value('id')]);
        // The former primary remains an explicit additional membership until reassigned.
        $this->delete(route('teams.destroy', $team))->assertSessionHasErrors('team');
        $member->teams()->detach($team->id);
        $this->delete(route('teams.destroy', $team))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertSame($owner->id, auth()->id());
    }

    public function test_work_and_release_references_are_preserved_when_team_deletion_is_requested(): void
    {
        $owner = $this->person(superadmin: true);
        $this->actingAs($owner);
        $client = Client::create(['name' => 'Fixture client', 'created_by' => $owner->id]);
        $projectTeam = Team::create(['name' => 'Project team', 'slug' => 'project-team']);
        Project::create(['name' => 'Preserved project', 'client_id' => $client->id, 'team_id' => $projectTeam->id, 'created_by' => $owner->id]);
        $taskTeam = Team::create(['name' => 'Task team', 'slug' => 'task-team']);
        Task::create(['title' => 'Preserved task', 'team_id' => $taskTeam->id, 'created_by' => $owner->id, 'work_type' => 'general']);
        $releaseTeam = Team::create(['name' => 'Release team', 'slug' => 'release-team']);
        $release = ReleaseNote::create(['version' => '1.0.0', 'summary' => 'Preserve targets', 'details' => 'Fixture release', 'publish_at' => now(), 'created_by' => $owner->id]);
        $release->targetTeams()->attach($releaseTeam->id);
        foreach ([$projectTeam, $taskTeam, $releaseTeam] as $team) {
            $this->delete(route('teams.destroy', $team))->assertSessionHasErrors('team');
            $this->assertDatabaseHas('teams', ['id' => $team->id]);
            $this->assertFalse(AuditLog::where('subject_type', Team::class)->where('subject_id', $team->id)->where('event', 'team_deleted')->exists());
        }
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('release_note_team', 1);
    }

    public function test_unused_built_in_teams_can_be_deleted_and_recreated_without_inventing_memberships(): void
    {
        $owner = $this->person(superadmin: true);
        $team = Team::where('slug', 'graphics')->firstOrFail();
        $this->actingAs($owner)->withSession(['active_dashboard_team' => 'graphics'])->delete(route('teams.destroy', $team))->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('active_dashboard_team');
        $this->assertDatabaseMissing('teams', ['slug' => 'graphics']);
        $this->get(route('dashboard.team', 'graphics'))->assertNotFound();
        $this->post(route('teams.store'), ['name' => 'Creative', 'slug' => 'graphics'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('team_user', 1);
    }

    public function test_custom_team_dashboard_uses_general_work_and_superadmin_can_switch_without_membership(): void
    {
        $owner = $this->person(superadmin: true);
        $team = Team::create(['name' => 'Customer success', 'slug' => 'customer-success']);
        $member = User::factory()->create(['team_id' => $team->id]);
        $outsider = $this->person('graphics');
        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('dashboard.team', $team->slug));
        $this->get(route('dashboard.team', $team->slug))->assertOk()->assertSee('Customer success')->assertSee('Team work');
        $this->actingAs($outsider)->get(route('dashboard.team', $team->slug))->assertForbidden();
        $this->actingAs($owner)->get(route('dashboard.team', $team->slug))->assertOk()->assertSee(route('dashboard.team', $team->slug), false);
        $this->assertFalse($owner->teams()->whereKey($team->id)->exists());
    }

    public function test_deleted_workflow_teams_do_not_leave_broken_creation_links(): void
    {
        $owner = $this->person(superadmin: true);
        $this->actingAs($owner);
        foreach (['developer', 'operations', 'marketing'] as $slug) {
            $team = Team::where('slug', $slug)->firstOrFail();
            $this->delete(route('teams.destroy', $team))->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->get(route('tasks.index'))->assertOk()->assertSee('Add task')->assertDontSee('Add Developer update')->assertDontSee('Add Operations update')->assertDontSee('Add Marketing update');
        $this->get(route('development.index'))->assertOk()->assertDontSee('New setup request');
        $this->get(route('work.create', 'developer'))->assertNotFound();
        $this->get(route('work.create', 'operations'))->assertNotFound();
        $this->assertTrue($owner->isAdmin());
    }
}
