<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportSecurityTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            File::delete($path);
        }
        parent::tearDown();
    }

    private function csv(string $phone): string
    {
        $path = tempnam(sys_get_temp_dir(), 'oms-import-security-');
        $this->files[] = $path;
        file_put_contents($path, "name,email,phone,primary_team,additional_teams\nFixture New Employee,security-fixture@example.test,{$phone},Marketing,Graphics\n");

        return $path;
    }

    public function test_late_database_failure_rolls_back_every_import_record_and_sanitizes_output(): void
    {
        DB::statement("CREATE TRIGGER fixture_reject_profile BEFORE INSERT ON employees BEGIN SELECT RAISE(ABORT, 'PRIVATE-FIXTURE-DB-DETAIL'); END");

        $exit = Artisan::call('oms:import-employees', ['file' => $this->csv('01900000001')]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('rolled back', $output);
        foreach (['PRIVATE-FIXTURE-DB-DETAIL', '01900000001', 'security-fixture@example.test', 'Fixture New Employee', '$2y$'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('team_user', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_import_normalizes_phone_and_keeps_initial_secrets_out_of_console_and_audit(): void
    {
        $this->assertSame(0, Artisan::call('oms:import-employees', ['file' => $this->csv('+8801900000001')]));
        $firstOutput = Artisan::output();
        $user = User::where('email', 'security-fixture@example.test')->firstOrFail();
        $initialHash = $user->password;
        $this->assertSame('01900000001', $user->phone);
        $this->assertSame('01900000001', $user->employee->phone);
        $this->assertTrue(Hash::check('01900000001', $initialHash));
        $this->assertFalse($user->must_change_password);

        $this->assertSame(0, Artisan::call('oms:import-employees', ['file' => $this->csv('8801900000002')]));
        $secondOutput = Artisan::output();
        $user = $user->fresh();
        $this->assertSame('01900000002', $user->phone);
        $this->assertSame($initialHash, $user->password);
        $this->assertSame('01900000002', $user->employee->phone);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('team_user', 2);
        $audit = AuditLog::all()->toJson();
        foreach (['01900000001', '01900000002', '+8801900000001', '8801900000002', $initialHash] as $secret) {
            $this->assertStringNotContainsString($secret, $firstOutput.$secondOutput.$audit);
        }
    }
}
