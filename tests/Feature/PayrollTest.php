<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Team;
use App\Models\User;
use App\Support\DecimalMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function staff(string $team, bool $authorized = false, array $attributes = []): User
    {
        return User::factory()->create($attributes + ['team_id' => Team::where('slug', $team)->value('id'), 'active' => true, 'payroll_authorized' => $authorized]);
    }

    private function employee(User $user, string $salary = '45000.10'): Employee
    {
        return Employee::create(['user_id' => $user->id, 'employee_code' => 'EMP-'.$user->id, 'job_title' => 'Software engineer', 'joined_on' => '2026-01-01', 'base_salary' => $salary]);
    }

    private function draft(User $operator): PayrollRun
    {
        $this->actingAs($operator)->post(route('payroll.store'), ['period' => '2026-11', 'notes' => 'November payroll'])->assertRedirect();

        return PayrollRun::where('period', '2026-11')->firstOrFail();
    }

    public function test_payroll_review_approval_correction_payment_and_print_flow(): void
    {
        $ops = $this->staff('operations', true);
        $admin = $this->staff('admin');
        $employee = $this->employee($this->staff('developer'));
        $run = $this->draft($ops);
        $entry = $run->entries()->firstOrFail();
        $this->actingAs($ops)->patch(route('payroll.entries.update', [$run, $entry]), ['base_salary' => '45000.10', 'allowances' => '1250.20', 'deductions' => '1000.10'])->assertRedirect();
        $this->assertSame('45250.20', $entry->fresh()->net_pay);
        $this->get(route('payroll.show', $run))->assertOk()->assertSee('45,250.20');
        $this->post(route('payroll.submit', $run))->assertRedirect();
        $this->post(route('payroll.approve', $run))->assertForbidden();
        $this->actingAs($admin)->post(route('payroll.approve', $run))->assertRedirect();
        $this->assertSame('approved', $run->fresh()->status);
        $this->actingAs($ops)->patch(route('payroll.entries.update', [$run, $entry]), ['base_salary' => '1.00', 'allowances' => '0.00', 'deductions' => '0.00'])->assertForbidden();
        $correction = ['base_salary' => '45000.10', 'allowances' => '1500.20', 'deductions' => '1000.10', 'reason' => 'Allowance corrected after attendance review.'];
        $this->post(route('payroll.correct', [$run, $entry]), $correction)->assertForbidden();
        $this->actingAs($admin)->post(route('payroll.correct', [$run, $entry]), $correction)->assertRedirect();
        $this->assertSame('45250.20', $entry->fresh()->net_pay);
        $this->assertSame('45500.20', $entry->fresh('corrections')->effective()->net_pay);
        $this->assertDatabaseHas('payroll_corrections', ['payroll_entry_id' => $entry->id, 'created_by' => $admin->id, 'reason' => $correction['reason']]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => PayrollRun::class, 'subject_id' => $run->id, 'event' => 'payroll.correction']);
        $this->get(route('payroll.payslip', [$run, $entry]))->assertOk()->assertSee($employee->employee_code)->assertSee('45,500.20');
        $csv = $this->get(route('payroll.export', $run))->assertOk()->assertDownload('payroll-2026-11.csv')->streamedContent();
        $this->assertStringContainsString('45500.20', $csv);
        $this->assertStringContainsString($entry->employee_code, $csv);
        $this->actingAs($ops)->post(route('payroll.pay', $run))->assertRedirect();
        $this->assertSame('paid', $run->fresh()->status);
        $this->actingAs($admin)->post(route('payroll.correct', [$run, $entry]), $correction)->assertStatus(409);
    }

    public function test_other_teams_and_unauthorized_operations_cannot_read_salaries_or_exports(): void
    {
        $operator = $this->staff('operations', true);
        $employee = $this->employee($this->staff('developer'));
        $run = $this->draft($operator);
        $entry = $run->entries()->firstOrFail();
        foreach (['marketing', 'graphics', 'developer', 'operations'] as $team) {
            $user = $this->staff($team);
            foreach ([route('employees.index'), route('employees.create'), route('employees.show', $employee), route('employees.edit', $employee), route('payroll.index'), route('payroll.create'), route('payroll.show', $run), route('payroll.export', $run), route('payroll.payslip', [$run, $entry])] as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
            $this->post(route('payroll.submit', $run))->assertForbidden();
            $this->post(route('payroll.pay', $run))->assertForbidden();
        }
    }

    public function test_duplicate_periods_are_safe_and_inactive_employees_are_not_added(): void
    {
        $operator = $this->staff('operations', true);
        $this->employee($this->staff('developer'));
        $this->employee($this->staff('graphics', false, ['active' => false]));
        $run = $this->draft($operator);
        $this->assertSame(1, $run->entries()->count());
        $this->post(route('payroll.store'), ['period' => '2026-11'])->assertSessionHasErrors('period');
        $this->assertSame(1, PayrollRun::count());
        $this->assertSame(1, PayrollEntry::count());
    }

    public function test_invalid_money_and_negative_net_are_rejected_without_changing_entry(): void
    {
        $operator = $this->staff('operations', true);
        $this->employee($this->staff('developer'));
        $run = $this->draft($operator);
        $entry = $run->entries()->firstOrFail();
        $this->patch(route('payroll.entries.update', [$run, $entry]), ['base_salary' => '1.005', 'allowances' => '0', 'deductions' => '0'])->assertSessionHasErrors('base_salary');
        $this->patch(route('payroll.entries.update', [$run, $entry]), ['base_salary' => '1.00', 'allowances' => '0', 'deductions' => '2.00'])->assertSessionHasErrors('deductions');
        $this->assertSame('45000.10', $entry->fresh()->net_pay);
        $this->assertSame('0.20', DecimalMoney::net('0.10', '0.20', '0.10'));
    }

    public function test_csv_protects_employee_names_against_spreadsheet_formula_injection(): void
    {
        $operator = $this->staff('operations', true);
        $this->employee($this->staff('developer', false, ['name' => '=SUM(1,2)']));
        $run = $this->draft($operator);
        $content = $this->get(route('payroll.export', $run))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $content);
    }

    public function test_only_existing_accounts_can_be_linked_and_employee_identity_is_preserved(): void
    {
        $operator = $this->staff('operations', true);
        $developer = $this->staff('developer');
        $this->actingAs($operator)->post(route('employees.store'), ['user_id' => $developer->id, 'employee_code' => 'BD-001', 'job_title' => 'Developer', 'joined_on' => '2026-01-01', 'base_salary' => '55000.25'])->assertRedirect();
        $employee = Employee::firstOrFail();
        $this->get(route('employees.index'))->assertOk()->assertSee('BD-001');
        $this->get(route('employees.show', $employee))->assertOk()->assertSee('55,000.25');
        $this->put(route('employees.update', $employee), ['user_id' => $operator->id, 'employee_code' => 'BD-001', 'job_title' => 'Senior developer', 'joined_on' => '2026-01-01', 'base_salary' => '60000.25'])->assertRedirect();
        $this->assertSame($developer->id, $employee->fresh()->user_id);
    }
}
