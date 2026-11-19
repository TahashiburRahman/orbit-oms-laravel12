@extends('layouts.app')
@section('title', 'Employee records')
@section('content')
<div class="page-header"><div><p class="muted">PEOPLE & PAYROLL</p><h1>Employee records</h1><p class="muted">Employment details and base salaries. Access is restricted to payroll staff.</p></div><a class="btn" href="{{ route('employees.create') }}">Add employee record</a></div>
<div class="card form-card"><form method="get" class="actions"><label class="field">Search name or employee code<input name="search" value="{{ request('search') }}" placeholder="Name or EMP-001"></label><button class="btn btn-secondary">Search</button></form></div>
<div class="card table-wrap"><table aria-label="Employee records"><thead><tr><th scope="col">Employee</th><th scope="col">Code</th><th scope="col">Team / role</th><th scope="col">Joined</th><th scope="col">Base salary</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
@forelse($employees as $employee)<tr><td><a href="{{ route('employees.show', $employee) }}">{{ $employee->user->name }}</a>@unless($employee->user->active)<span class="badge">Inactive</span>@endunless</td><td>{{ $employee->employee_code }}</td><td>{{ $employee->user->team?->name }}<br><span class="muted">{{ $employee->job_title }}</span></td><td>{{ $employee->joined_on->format('d M Y') }}</td><td>{{ \App\Support\DecimalMoney::display($employee->base_salary) }}</td><td><a href="{{ route('employees.edit', $employee) }}">Edit</a></td></tr>
@empty<tr><td colspan="6" class="empty-state">No employee records yet. Add records for your existing user accounts to prepare payroll.</td></tr>@endforelse
</tbody></table>{{ $employees->links() }}</div>
@endsection
