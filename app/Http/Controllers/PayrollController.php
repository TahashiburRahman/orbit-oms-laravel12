<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\Audit;
use App\Support\DecimalMoney;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('create', PayrollRun::class);
        $runs = PayrollRun::visibleTo($request->user())->withCount('entries')->orderByDesc('period')->paginate(15);

        return view('payroll.index', compact('runs'));
    }

    public function create()
    {
        Gate::authorize('create', PayrollRun::class);

        return view('payroll.form', ['run' => new PayrollRun]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', PayrollRun::class);
        $data = $request->validate(['period' => ['required', 'date_format:Y-m', Rule::unique('payroll_runs', 'period')], 'notes' => ['nullable', 'string', 'max:5000']]);
        $run = DB::transaction(function () use ($request, $data) {
            $employees = Employee::visibleTo($request->user())->whereHas('user', fn ($q) => $q->where('active', true))->with('user')->get();
            if ($employees->isEmpty()) {
                throw ValidationException::withMessages(['period' => 'Create at least one active employee record before preparing payroll.']);
            }
            $run = PayrollRun::create($data + ['created_by' => $request->user()->id, 'status' => 'draft']);
            foreach ($employees as $employee) {
                $run->entries()->create([
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->user->name,
                    'employee_code' => $employee->employee_code,
                    'base_salary' => $employee->base_salary,
                    'allowances' => '0.00', 'deductions' => '0.00', 'net_pay' => $employee->base_salary,
                ]);
            }

            return $run;
        });

        return redirect()->route('payroll.show', $run)->with('success', 'Draft payroll prepared from active employee records. Review every entry before submitting.');
    }

    public function show(PayrollRun $payroll)
    {
        Gate::authorize('view', $payroll);

        return view('payroll.show', ['run' => $payroll->load('entries.corrections.author')]);
    }

    public function edit(PayrollRun $payroll)
    {
        Gate::authorize('update', $payroll);

        return view('payroll.form', ['run' => $payroll]);
    }

    public function update(Request $request, PayrollRun $payroll)
    {
        Gate::authorize('update', $payroll);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:5000']]);
        DB::transaction(function () use ($payroll, $data) {
            $locked = PayrollRun::lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'draft', 409, 'Only draft payroll can be edited.');
            $locked->update($data);
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll notes updated.');
    }

    public function updateEntry(Request $request, PayrollRun $payroll, PayrollEntry $entry)
    {
        Gate::authorize('update', $payroll);
        abort_unless($entry->payroll_run_id === $payroll->id, 404);
        $data = $this->moneyData($request);
        DB::transaction(function () use ($payroll, $entry, $data) {
            $locked = PayrollRun::lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'draft', 409, 'Only draft payroll entries can be edited.');
            $entry->update($data);
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll entry updated.');
    }

    public function submit(Request $request, PayrollRun $payroll)
    {
        Gate::authorize('view', $payroll);
        DB::transaction(function () use ($payroll) {
            $locked = PayrollRun::lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'draft' && $locked->entries()->exists(), 409, 'Only a populated draft can be submitted.');
            $locked->update(['status' => 'submitted', 'submitted_at' => now()]);
            Notify::send(User::where('active', true)->inTeams('admin')->get(), 'Payroll '.$locked->period.' awaits approval', route('payroll.show', $locked));
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Submitted to Admin for approval.');
    }

    public function approve(Request $request, PayrollRun $payroll)
    {
        Gate::authorize('view', $payroll);
        abort_unless($request->user()->isAdmin(), 403);
        DB::transaction(function () use ($request, $payroll) {
            $locked = PayrollRun::lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'submitted' && $locked->entries()->exists(), 409, 'Only submitted payroll can be approved.');
            $locked->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            Notify::send($this->payrollUsers(), 'Payroll '.$locked->period.' approved', route('payroll.show', $locked));
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payroll approved. Original entries are now preserved.');
    }

    public function pay(Request $request, PayrollRun $payroll)
    {
        Gate::authorize('view', $payroll);
        DB::transaction(function () use ($request, $payroll) {
            $locked = PayrollRun::lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'approved', 409, 'Only approved payroll can be recorded as paid.');
            $locked->update(['status' => 'paid', 'paid_by' => $request->user()->id, 'paid_at' => now()]);
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Payment recorded. No payment was sent by this application.');
    }

    public function correct(Request $request, PayrollRun $payroll, PayrollEntry $entry)
    {
        Gate::authorize('view', $payroll);
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($entry->payroll_run_id === $payroll->id, 404);
        $data = $this->moneyData($request);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:3000']])['reason'];
        DB::transaction(function () use ($request, $payroll, $entry, $data, $reason) {
            $locked = PayrollRun::lockForUpdate()->findOrFail($payroll->id);
            abort_unless($locked->status === 'approved', 409, 'Corrections are permitted only before approved payroll is marked paid.');
            $prior = $entry->fresh('corrections')->effective()->only(['base_salary', 'allowances', 'deductions', 'net_pay']);
            $entry->corrections()->create($data + ['created_by' => $request->user()->id, 'reason' => $reason, 'prior_values' => $prior]);
            Audit::record($locked, 'payroll.correction', ['entry_id' => $entry->id, 'reason' => $reason, 'prior' => $prior, 'corrected' => $data]);
            Notify::send($this->payrollUsers(), 'Payroll '.$locked->period.' corrected', route('payroll.show', $locked));
        });

        return redirect()->route('payroll.show', $payroll)->with('success', 'Correction added. The approved original entry remains in history.');
    }

    public function payslip(PayrollRun $payroll, PayrollEntry $entry)
    {
        Gate::authorize('view', $payroll);
        abort_unless($entry->payroll_run_id === $payroll->id, 404);

        return view('payroll.payslip', ['run' => $payroll, 'entry' => $entry->load('corrections.author')]);
    }

    public function export(PayrollRun $payroll)
    {
        Gate::authorize('view', $payroll);
        $payroll->load('entries.corrections');

        return response()->streamDownload(function () use ($payroll) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Period', 'Employee', 'Employee code', 'Base salary (BDT)', 'Allowances (BDT)', 'Deductions (BDT)', 'Net pay (BDT)', 'Status']);
            foreach ($payroll->entries as $entry) {
                $effective = $entry->effective();
                fputcsv($stream, array_map(fn ($value) => preg_match('/^(?:[=+\-@\t\r\n]|\s+[=+\-@])/u', (string) $value) ? "'".$value : $value, [
                    $payroll->period, $entry->employee_name, $entry->employee_code,
                    $effective->base_salary, $effective->allowances, $effective->deductions, $effective->net_pay, $payroll->status,
                ]));
            }
            fclose($stream);
        }, 'payroll-'.$payroll->period.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function moneyData(Request $request): array
    {
        $data = $request->validate([
            'base_salary' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'],
            'allowances' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'],
            'deductions' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'],
        ]);
        $data['net_pay'] = DecimalMoney::net((string) $data['base_salary'], (string) $data['allowances'], (string) $data['deductions']);

        return $data;
    }

    private function payrollUsers()
    {
        return User::where('active', true)->where(fn ($q) => $q->inTeams('admin')->orWhere(fn ($q) => $q->inTeams('operations')->where('payroll_authorized', true)))->get();
    }
}
