<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brief;
use App\Models\Deliverable;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirstLoginPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function employee(string $team = 'marketing', bool $temporary = true, string $password = 'Temporary-fixture-password'): User
    {
        return User::factory()->create([
            'team_id' => Team::where('slug', $team)->value('id'),
            'must_change_password' => $temporary,
            'password' => $password,
        ]);
    }

    public function test_phone_password_login_opens_the_primary_dashboard_without_a_required_password_change(): void
    {
        $phone = '01900000001';
        $user = $this->employee(password: $phone);
        $hash = $user->password;
        $this->post(route('login.store'), ['email' => '  '.strtoupper($user->email).'  ', 'password' => $phone])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertRedirect(route('dashboard.team', 'marketing'));
        $this->get(route('dashboard.team', 'marketing'))->assertOk()->assertSee(route('password.change'), false);
        foreach (['/clients', '/projects', '/tasks', '/notifications'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('password.change'))->assertOk();
        $this->assertSame($hash, $user->fresh()->password);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_optional_password_changes_keep_employee_financial_and_file_permissions_intact(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('fixtures/private.pdf', 'Fixture PDF content');
        $admin = $this->employee('admin');
        $marketer = $this->employee('marketing');
        $profile = Employee::create(['user_id' => $admin->id, 'employee_code' => 'FIXTURE-001', 'job_title' => 'Fixture role', 'joined_on' => today(), 'base_salary' => '100.00']);
        $run = PayrollRun::create(['period' => '2026-01', 'status' => 'draft', 'created_by' => $admin->id]);
        $entry = PayrollEntry::create(['payroll_run_id' => $run->id, 'employee_id' => $profile->id, 'employee_name' => 'Fixture Employee', 'employee_code' => $profile->employee_code, 'base_salary' => '100.00', 'allowances' => '0.00', 'deductions' => '0.00', 'net_pay' => '100.00']);
        $brief = Brief::create(['title' => 'Private fixture brief', 'type' => 'graphic', 'description' => 'Fixture description', 'status' => 'open', 'requested_by' => $admin->id]);
        $file = Deliverable::create(['brief_id' => $brief->id, 'version' => 1, 'uploaded_by' => $admin->id, 'path' => 'fixtures/private.pdf', 'original_name' => 'private.pdf', 'mime' => 'application/pdf', 'size' => 12, 'status' => 'draft']);

        $this->actingAs($marketer);
        foreach ([route('users.show', $admin), route('employees.show', $profile), route('payroll.show', $run), route('payroll.export', $run), route('payroll.payslip', [$run, $entry]), route('files.download', $file), route('audit.index')] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('payroll.submit', $run))->assertForbidden();
        $this->assertSame('draft', $run->fresh()->status);

        $this->actingAs($admin);
        foreach ([route('users.show', $admin), route('employees.show', $profile), route('payroll.show', $run), route('payroll.export', $run), route('payroll.payslip', [$run, $entry]), route('audit.index')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('files.download', $file))->assertDownload('private.pdf');
        $this->post(route('payroll.submit', $run))->assertRedirect(route('payroll.show', $run));
        $this->assertSame('submitted', $run->fresh()->status);
    }

    public function test_phone_password_can_be_changed_voluntarily_from_the_dashboard(): void
    {
        $temporary = '01900000001';
        $user = $this->employee(password: $temporary);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => $temporary])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard.team', 'marketing'))->assertOk();
        $this->put(route('password.update'), ['current_password' => $temporary, 'password' => $temporary, 'password_confirmation' => $temporary])->assertSessionHasErrors('password');
        $this->put(route('password.update'), ['current_password' => $temporary, 'password' => 'A-strong-fixture-password', 'password_confirmation' => 'A-strong-fixture-password'])->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('A-strong-fixture-password', $user->fresh()->password));
    }

    public function test_current_password_confirmation_strength_and_difference_are_required_without_flashing_secrets(): void
    {
        $user = $this->employee();
        $this->actingAs($user)->from(route('password.change'));
        $original = $user->password;
        $this->put(route('password.update'), ['current_password' => 'Wrong-fixture-password', 'password' => 'A-new-fixture-password', 'password_confirmation' => 'A-new-fixture-password'])->assertSessionHasErrors('current_password');
        $this->put(route('password.update'), ['current_password' => 'Temporary-fixture-password', 'password' => 'Temporary-fixture-password', 'password_confirmation' => 'Temporary-fixture-password'])->assertSessionHasErrors('password');
        $this->put(route('password.update'), ['current_password' => 'Temporary-fixture-password', 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertSame($original, $user->fresh()->password);
        $this->assertTrue($user->fresh()->must_change_password);
        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $this->assertNull(session()->getOldInput($field));
        }
    }

    public function test_password_change_updates_only_the_authenticated_account_and_revokes_other_sessions(): void
    {
        $user = $this->employee('operations', temporary: false);
        $other = $this->employee('admin');
        $otherHash = $other->password;
        DB::table('sessions')->insert(['id' => 'old-fixture-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Fixture', 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'Temporary-fixture-password',
            'password' => 'A-replacement-fixture-password',
            'password_confirmation' => 'A-replacement-fixture-password',
            'user_id' => $other->id,
            'team_id' => $other->team_id,
            'payroll_authorized' => true,
        ])->assertRedirect(route('dashboard'));

        $changed = $user->fresh();
        $this->assertFalse($changed->must_change_password);
        $this->assertNotNull($changed->password_changed_at);
        $this->assertTrue(Hash::check('A-replacement-fixture-password', $changed->password));
        $this->assertFalse(Hash::check('Temporary-fixture-password', $changed->password));
        $this->assertNull($changed->remember_token);
        $this->assertSame($user->team_id, $changed->team_id);
        $this->assertFalse($changed->payroll_authorized);
        $this->assertSame($otherHash, $other->fresh()->password);
        $this->assertTrue($other->fresh()->must_change_password);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-fixture-session']);
        $this->get(route('dashboard'))->assertRedirect(route('dashboard.team', 'operations'));
        $this->get(route('dashboard.team', 'operations'))->assertOk();
        $this->get(route('password.change'))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['subject_type' => User::class, 'subject_id' => $user->id, 'event' => 'password_changed']);
        $audit = AuditLog::where('subject_type', User::class)->where('subject_id', $user->id)->get()->toJson();
        $this->assertStringNotContainsString($changed->password, $audit);
        $this->assertStringNotContainsString('Temporary-fixture-password', $audit);
        $this->assertStringNotContainsString('A-replacement-fixture-password', $audit);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Temporary-fixture-password'])->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'A-replacement-fixture-password'])->assertRedirect(route('dashboard'));
    }

    public function test_an_employee_can_change_their_password_more_than_once(): void
    {
        $user = $this->employee(temporary: false);
        $this->actingAs($user)->get(route('password.change'))->assertOk();
        $this->put(route('password.update'), [
            'current_password' => 'Temporary-fixture-password',
            'password' => 'First-voluntary-fixture-password',
            'password_confirmation' => 'First-voluntary-fixture-password',
        ])->assertRedirect(route('dashboard'));
        $firstHash = $user->fresh()->password;
        $this->get(route('password.change'))->assertOk();
        $this->put(route('password.update'), [
            'current_password' => 'First-voluntary-fixture-password',
            'password' => 'Second-voluntary-fixture-password',
            'password_confirmation' => 'Second-voluntary-fixture-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertNotSame($firstHash, $user->fresh()->password);
        $this->assertTrue(Hash::check('Second-voluntary-fixture-password', $user->fresh()->password));
        $this->assertSame(2, AuditLog::where('subject_type', User::class)->where('subject_id', $user->id)->where('event', 'password_changed')->count());
    }

    public function test_password_change_routes_require_an_active_authenticated_account(): void
    {
        $this->get(route('password.change'))->assertRedirect(route('login'));
        $this->put(route('password.update'), [])->assertRedirect(route('login'));
        $inactive = $this->employee();
        $inactive->update(['active' => false]);
        $this->actingAs($inactive)->get(route('password.change'))->assertRedirect(route('login'));
    }
}
