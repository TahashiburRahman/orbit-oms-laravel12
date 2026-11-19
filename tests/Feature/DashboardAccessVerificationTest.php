<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardAccessVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $rosterPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        File::ensureDirectoryExists(storage_path('framework/testing'));
        $this->rosterPath = storage_path('framework/testing/dashboard-access-roster-'.uniqid().'.csv');
    }

    protected function tearDown(): void
    {
        File::delete($this->rosterPath);
        parent::tearDown();
    }

    private function rosterUser(string $email, string $primary, array $additional = []): User
    {
        $user = User::factory()->create(['email' => $email, 'name' => 'Fictional Staff', 'phone' => '01700000001', 'password' => 'A-fixture-only-password', 'team_id' => Team::where('slug', $primary)->value('id'), 'must_change_password' => true]);
        $user->teams()->syncWithoutDetaching(Team::whereIn('slug', $additional)->pluck('id')->all());

        return $user;
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['users', 'employees', 'team_user', 'audit_logs', 'sessions'] as $table) {
            $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    public function test_real_accounts_are_rendered_and_denied_without_any_database_mutation_or_identifier_output(): void
    {
        $single = $this->rosterUser('single@example.test', 'marketing');
        $dual = $this->rosterUser('dual@example.test', 'operations', ['developer']);
        $admin = $this->rosterUser('secondary-admin@example.test', 'graphics', ['admin']);
        DB::table('sessions')->insert(['id' => 'fictional-existing-session', 'user_id' => $dual->id, 'payload' => base64_encode('fictional session payload'), 'last_activity' => time()]);
        File::put($this->rosterPath, "name,email,phone,primary_team,additional_teams\nFictional Staff,single@example.test,01700000001,Marketing,\nFictional Staff,dual@example.test,01700000001,Operations,Development\nFictional Staff,secondary-admin@example.test,01700000001,Graphics,Admin\n");
        $before = $this->snapshot();
        $this->assertSame(0, Artisan::call('oms:verify-dashboard-access', ['file' => $this->rosterPath]));
        $output = Artisan::output();
        $this->assertStringContainsString('Accounts checked: 3', $output);
        $this->assertStringContainsString('Required memberships checked: 5', $output);
        $this->assertStringContainsString('Password settings rendered: 3', $output);
        $this->assertStringContainsString('Assigned dashboards rendered: 5', $output);
        $this->assertStringContainsString('Dual team accounts checked: 2', $output);
        $this->assertStringContainsString('Unassigned dashboards denied: 10', $output);
        foreach ([$single, $dual, $admin] as $user) {
            $this->assertStringNotContainsString($user->email, $output);
            $this->assertStringNotContainsString($user->password, $output);
            $this->assertStringNotContainsString($user->phone, $output);
            $this->assertTrue($user->fresh()->must_change_password);
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_missing_imported_account_fails_with_a_generic_message_and_no_data_changes(): void
    {
        File::put($this->rosterPath, "name,email,phone,primary_team,additional_teams\nFictional Staff,missing@example.test,01700000001,Marketing,\n");
        $before = $this->snapshot();
        $this->assertSame(1, Artisan::call('oms:verify-dashboard-access', ['file' => $this->rosterPath]));
        $this->assertStringNotContainsString('missing@example.test', Artisan::output());
        $this->assertSame($before, $this->snapshot());
    }
}
