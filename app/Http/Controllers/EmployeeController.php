<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('create', Employee::class);
        $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $employees = Employee::visibleTo($request->user())->with('user.team')
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($q) => $q->where('employee_code', 'like', '%'.$search.'%')->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))))
            ->orderBy('employee_code')->paginate(15)->withQueryString();

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        Gate::authorize('create', Employee::class);

        return view('employees.form', ['employee' => new Employee, 'users' => User::where('active', true)->whereDoesntHave('employee')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Employee::class);
        $employee = Employee::create($this->validated($request));

        return redirect()->route('employees.show', $employee)->with('success', 'Employee record created.');
    }

    public function show(Employee $employee)
    {
        Gate::authorize('view', $employee);

        return view('employees.show', ['employee' => $employee->load('user.team')]);
    }

    public function edit(Employee $employee)
    {
        Gate::authorize('update', $employee);

        return view('employees.form', ['employee' => $employee, 'users' => User::whereKey($employee->user_id)->get()]);
    }

    public function update(Request $request, Employee $employee)
    {
        Gate::authorize('update', $employee);
        $data = $this->validated($request, $employee);
        unset($data['user_id']); // Preserve employee identity across every payroll period.
        $employee->update($data);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee record updated.');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'user_id' => [$employee ? 'sometimes' : 'required', 'integer', Rule::exists('users', 'id')->when(! $employee, fn ($q) => $q->where('active', true)), Rule::unique('employees', 'user_id')->ignore($employee?->id)],
            'employee_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('employees', 'employee_code')->ignore($employee?->id)],
            'job_title' => ['required', 'string', 'max:255'],
            'joined_on' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:3000'],
            'base_salary' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'],
        ]);
    }

    public function destroy(Request $request, Employee $employee)
    {
        app(\App\Support\AccountRemoval::class)->removeProfile($request->user(), $employee);

        return redirect()->route('employees.index')->with('success', 'Employee profile deleted. The login account is retained.');
    }
}
