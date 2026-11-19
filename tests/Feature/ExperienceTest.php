<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ReleaseNote;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceTest extends TestCase
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

    public function test_every_team_has_its_own_scoped_dashboard_and_menu(): void
    {
        $admin = $this->staff('admin');
        foreach (['admin', 'operations', 'marketing', 'graphics', 'developer'] as $slug) {
            $user = $slug === 'admin' ? $admin : $this->staff($slug);
            $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('dashboard.team', $slug));
            $response = $this->get(route('dashboard.team', $slug))->assertOk()->assertSee('Your task board');
            if ($slug !== 'admin') {
                $response->assertDontSee('People &amp; teams', false)->assertDontSee('Activity history');
                $this->get(route('dashboard.team', 'admin'))->assertForbidden();
            }
        }
        $developer = $this->staff('developer');
        $this->actingAs($admin);
        Task::create(['title' => 'Private admin financial review', 'team_id' => $admin->team_id, 'assigned_to' => $admin->id, 'created_by' => $admin->id, 'status' => 'blocked', 'priority' => 'high', 'work_type' => 'general']);
        Task::create(['title' => 'Assigned courier setup', 'team_id' => $developer->team_id, 'assigned_to' => $developer->id, 'created_by' => $admin->id, 'status' => 'open', 'priority' => 'normal', 'work_type' => 'courier']);
        $this->actingAs($developer)->get(route('dashboard.team', 'developer'))->assertOk()->assertSee('Assigned courier setup')->assertDontSee('Private admin financial review');
    }

    public function test_releases_are_admin_managed_targeted_scheduled_and_notified_once(): void
    {
        $admin = $this->staff('admin');
        $marketer = $this->staff('marketing');
        $developer = $this->staff('developer');
        $payload = ['version' => 'v2.1.0', 'summary' => 'Marketing reports improved', 'details' => 'New campaign reporting is available for the marketing team.', 'status' => 'published', 'publish_at' => now()->addDay()->format('Y-m-d H:i:s'), 'teams' => [$marketer->team_id]];
        $this->actingAs($marketer)->post(route('releases.store'), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('releases.store'), $payload)->assertRedirect();
        $release = ReleaseNote::where('version', 'v2.1.0')->firstOrFail();
        $this->assertDatabaseCount('notifications', 0);
        $this->actingAs($marketer)->get(route('releases.show', $release))->assertForbidden();
        $this->get(route('releases.index'))->assertOk()->assertDontSee($payload['summary']);
        $this->travel(2)->days();
        $this->artisan('releases:notify')->assertSuccessful();
        $this->artisan('releases:notify')->assertSuccessful();
        $this->assertSame(1, $marketer->notifications()->count());
        $this->assertSame(0, $developer->notifications()->count());
        $this->assertNotNull($release->fresh()->notified_at);
        $this->actingAs($marketer)->get(route('releases.show', $release))->assertOk()->assertSee($payload['summary']);
        $this->get(route('dashboard.team', 'marketing'))->assertOk()->assertSee($payload['summary']);
        $this->actingAs($developer)->get(route('releases.show', $release))->assertForbidden();
        $this->get(route('releases.index', ['q' => 'Marketing']))->assertOk()->assertDontSee($payload['summary']);
        $this->travelBack();
    }

    public function test_drafts_do_not_leak_and_admin_release_edits_are_audited(): void
    {
        $admin = $this->staff('admin');
        $developer = $this->staff('developer');
        $payload = ['version' => 'v2.2.0', 'summary' => 'Draft integration notes', 'details' => 'Internal draft.', 'status' => 'draft', 'publish_at' => null, 'teams' => [$developer->team_id]];
        $this->actingAs($admin)->get(route('releases.create'))->assertOk()->assertSee('Target teams');
        $this->actingAs($admin)->post(route('releases.store'), $payload)->assertRedirect();
        $release = ReleaseNote::where('version', 'v2.2.0')->firstOrFail();
        $this->get(route('releases.edit', $release))->assertOk()->assertSee('v2.2.0');
        $this->actingAs($developer)->get(route('releases.index', ['q' => 'Draft']))->assertOk()->assertDontSee($payload['summary']);
        $this->get(route('releases.show', $release))->assertForbidden();
        $this->actingAs($admin)->put(route('releases.update', $release), array_replace($payload, ['status' => 'published', 'publish_at' => now()->subMinute()->format('Y-m-d H:i:s')]))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['subject_type' => ReleaseNote::class, 'subject_id' => $release->id, 'event' => 'updated']);
        $this->actingAs($developer)->get(route('releases.show', $release))->assertOk();
        $this->assertSame(1, $developer->notifications()->count());
    }

    public function test_notifications_are_owned_and_support_both_read_states(): void
    {
        $owner = $this->staff('developer');
        $other = $this->staff('graphics');
        Notify::send([$owner], 'Your assigned landing page task', route('tasks.index'));
        $notification = $owner->notifications()->firstOrFail();
        $this->actingAs($other)->get(route('notifications.index'))->assertOk()->assertDontSee('Your assigned landing page task');
        $this->post(route('notifications.read', $notification->id))->assertNotFound();
        $this->post(route('notifications.unread', $notification->id))->assertNotFound();
        $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee('Your assigned landing page task');
        $this->post(route('notifications.read', $notification->id))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->post(route('notifications.unread', $notification->id))->assertRedirect();
        $this->assertNull($notification->fresh()->read_at);
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $owner->unreadNotifications()->count());
    }

    public function test_audit_is_admin_only_and_entries_cannot_be_mutated(): void
    {
        $admin = $this->staff('admin');
        $other = $this->staff('operations');
        $this->actingAs($other)->get(route('audit.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('audit.index'))->assertOk()->assertSee('Activity history');
        $entry = AuditLog::firstOrFail();
        $this->expectException(\LogicException::class);
        $entry->update(['event' => 'rewritten']);
    }
}
