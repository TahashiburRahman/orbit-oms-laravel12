<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use App\Support\BangladeshPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportEmployeesTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function csv(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'oms-roster-test-');
        $this->files[] = $file;
        $handle = fopen($file, 'wb');
        fputcsv($handle, ['name', 'email', 'phone', 'primary_team', 'additional_teams']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $file;
    }

    public function test_import_normalizes_login_and_phone_and_creates_profiles_and_memberships(): void
    {
        $file = $this->csv([
            ['Example Owner', ' Owner@Example.test ', '+8801700000001', 'Admin', 'Development'],
            ['Example Coordinator', 'coordinator@example.test', '8801700000002', 'Operations', 'Developer'],
            ['Example Creative', 'creative@example.test', '01700000003', 'Marketing', 'Graphics'],
            ['Example Artist', 'artist@example.test', '01700000004', 'Graphics', ''],
        ]);
        $this->assertSame(0, Artisan::call('oms:import-employees', ['file' => $file]));
        $output = Artisan::output();
        $this->assertStringContainsString('Accounts created: 4', $output);
        $this->assertStringContainsString('Memberships added: 7', $output);
        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame('01700000001', $owner->phone);
        $this->assertTrue(Hash::check('01700000001', $owner->password));
        $this->assertFalse($owner->must_change_password);
        $this->assertTrue($owner->inTeam('admin', 'developer'));
        $this->assertSame(2, $owner->teams()->count());
        $this->assertSame('0.00', $owner->employee->base_salary);
        $this->assertSame($owner->phone, $owner->employee->phone);
        $this->assertFalse(User::where('email', 'coordinator@example.test')->firstOrFail()->canPayroll());
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('employees', 4);
        $this->assertDatabaseCount('team_user', 7);
        $this->assertSame(0, Artisan::call('oms:import-employees', ['file' => $file, '--verify' => true]));
        $this->assertStringContainsString('Required memberships verified: 7', Artisan::output());
        foreach (['owner@example.test', '01700000001', $owner->password] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_repeat_import_preserves_a_password_changed_voluntarily_from_dashboard(): void
    {
        $file = $this->csv([['Example Employee', 'employee@example.test', '01700000005', 'Marketing', 'Graphics']]);
        $this->artisan('oms:import-employees', ['file' => $file])->assertSuccessful();
        $this->post(route('login.store'), ['email' => ' EMPLOYEE@EXAMPLE.TEST ', 'password' => '01700000005'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route('dashboard.team', 'marketing'));
        $this->get(route('dashboard.team', 'marketing'))->assertOk();
        $this->get(route('password.change'))->assertOk();
        $this->put(route('password.update'), ['current_password' => '01700000005', 'password' => 'A-changed-private-password', 'password_confirmation' => 'A-changed-private-password'])->assertRedirect(route('dashboard'));
        $user = User::where('email', 'employee@example.test')->firstOrFail();
        $hash = $user->password;
        $changedAt = $user->password_changed_at->toDateTimeString();
        $this->get(route('dashboard'))->assertRedirect(route('dashboard.team', 'marketing'));
        $this->get(route('dashboard.team', 'marketing'))->assertOk();
        $this->get(route('dashboard.team', 'graphics'))->assertOk();
        $this->assertSame(0, Artisan::call('oms:import-employees', ['file' => $file]));
        $output = Artisan::output();
        $this->assertStringContainsString('Accounts created: 0', $output);
        $this->assertStringContainsString('Accounts unchanged: 1', $output);
        $this->assertStringContainsString('Memberships added: 0', $output);
        $this->assertStringContainsString('Existing passwords preserved: 1', $output);
        $user->refresh();
        $this->assertSame($hash, $user->password);
        $this->assertFalse($user->must_change_password);
        $this->assertSame($changedAt, $user->password_changed_at->toDateTimeString());
        $this->assertTrue(Hash::check('A-changed-private-password', $user->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('team_user', 2);
    }

    public function test_existing_profile_payroll_flags_extra_memberships_and_passwords_are_preserved(): void
    {
        $oldPrimary = Team::where('slug', 'graphics')->value('id');
        $user = User::factory()->create(['email' => 'existing@example.test', 'team_id' => $oldPrimary, 'active' => false, 'payroll_authorized' => true, 'password' => 'An-existing-private-password']);
        $user->teams()->attach(Team::where('slug', 'marketing')->value('id'));
        $profile = Employee::create(['user_id' => $user->id, 'employee_code' => 'EXAMPLE-42', 'job_title' => 'Senior specialist', 'joined_on' => '2020-01-02', 'phone' => '01700000006', 'base_salary' => '42000.25']);
        DB::table('users')->where('id', $user->id)->update(['email' => ' EXISTING@EXAMPLE.TEST ']);
        $hash = $user->password;
        $file = $this->csv([['Updated Example', 'existing@example.test', '+8801700000007', 'Operations', 'Development']]);
        $this->artisan('oms:import-employees', ['file' => $file])->assertSuccessful();
        $user->refresh();
        $profile->refresh();
        $this->assertSame($hash, $user->password);
        $this->assertFalse($user->active);
        $this->assertTrue($user->payroll_authorized);
        $this->assertFalse($user->must_change_password);
        $this->assertSame('operations', $user->team->slug);
        $this->assertSame('01700000007', $profile->phone);
        $this->assertSame('EXAMPLE-42', $profile->employee_code);
        $this->assertSame('Senior specialist', $profile->job_title);
        $this->assertSame('2020-01-02', $profile->joined_on->toDateString());
        $this->assertSame('42000.25', $profile->base_salary);
        $this->assertSame(4, $user->teams()->count());
        foreach (['operations', 'developer', 'marketing', 'graphics'] as $slug) {
            $this->assertTrue($user->inTeam($slug));
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_duplicate_normalized_emails_are_rejected_before_writing_anything(): void
    {
        $file = $this->csv([['Example One', 'one@example.test', '01700000008', 'Marketing', ''], ['Example Two', ' ONE@EXAMPLE.TEST ', '01700000009', 'Graphics', '']]);
        $this->artisan('oms:import-employees', ['file' => $file])->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('team_user', 0);
    }

    public function test_invalid_team_and_phone_do_not_partially_import_valid_rows(): void
    {
        foreach ([['Example Invalid', 'bad@example.test', '1700000010', 'Marketing', ''], ['Example Invalid', 'bad@example.test', '01700000010', 'Unknown team', '']] as $invalid) {
            $file = $this->csv([['Example Valid', 'valid@example.test', '01700000011', 'Operations', ''], $invalid]);
            $this->artisan('oms:import-employees', ['file' => $file])->assertFailed();
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_membership_migration_backfills_without_changing_primary_or_existing_records(): void
    {
        $user = User::factory()->create(['email' => 'legacy@example.test', 'team_id' => Team::where('slug', 'developer')->value('id')]);
        $profile = Employee::create(['user_id' => $user->id, 'employee_code' => 'LEGACY-1', 'job_title' => 'Engineer', 'joined_on' => '2021-05-01', 'base_salary' => '35000.00']);
        $primary = $user->team_id;
        $hash = $user->password;
        $migration = require database_path('migrations/2026_11_17_000600_add_team_memberships_and_onboarding.php');
        $migration->down();
        $migration->up();
        $user->refresh();
        $this->assertSame($primary, $user->team_id);
        $this->assertSame($hash, $user->password);
        $this->assertDatabaseHas('team_user', ['user_id' => $user->id, 'team_id' => $primary]);
        $this->assertDatabaseHas('employees', ['id' => $profile->id, 'employee_code' => 'LEGACY-1']);
        $this->assertFalse($user->must_change_password);
    }

    public function test_phone_normalization_keeps_the_leading_zero(): void
    {
        foreach (['01700000012', '8801700000012', '+8801700000012', '+88 (017) 0000-0012'] as $phone) {
            $this->assertSame('01700000012', BangladeshPhone::normalize($phone));
        }
        $this->expectException(\InvalidArgumentException::class);
        BangladeshPhone::normalize('1700000012');
    }
}
