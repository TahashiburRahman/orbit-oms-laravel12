<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptionalPasswordMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_removing_the_legacy_requirement_preserves_passwords_memberships_and_change_history(): void
    {
        $user = User::factory()->create(['team_id' => Team::where('slug', 'marketing')->value('id'), 'must_change_password' => true]);
        $changed = User::factory()->create(['team_id' => Team::where('slug', 'developer')->value('id'), 'must_change_password' => false, 'password_changed_at' => now()->subDay()]);
        $user->teams()->attach(Team::where('slug', 'graphics')->value('id'));
        $hashes = [$user->password, $changed->password];
        $teamIds = $user->teamIds();
        $changedAt = $changed->password_changed_at->toDateTimeString();
        $migration = require database_path('migrations/2026_11_17_000700_make_password_changes_optional.php');
        $migration->up();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame($hashes, [$user->fresh()->password, $changed->fresh()->password]);
        $this->assertSame($teamIds, $user->fresh()->teamIds());
        $this->assertSame($changedAt, $changed->fresh()->password_changed_at->toDateTimeString());
        $this->assertDatabaseHas('audit_logs', ['subject_type' => User::class, 'subject_id' => $user->id, 'event' => 'password_change_made_optional']);
    }
}
