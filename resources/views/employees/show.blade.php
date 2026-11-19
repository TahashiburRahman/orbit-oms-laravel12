@extends('layouts.app')
@section('title', $employee->user->name)
@section('content')
<div class="page-header"><div><p class="muted">EMPLOYEE · {{ $employee->employee_code }}</p><h1>{{ $employee->user->name }}</h1><p class="muted">{{ $employee->job_title }} · {{ $employee->user->team?->name }}</p></div><a class="btn" href="{{ route('employees.edit', $employee) }}">Edit employee</a></div>
<div class="card form-card grid-form"><div><h3>Contact</h3><p>{{ $employee->user->email }}</p><p>{{ $employee->phone ?: 'Phone not recorded' }}</p><p class="muted">{{ $employee->address ?: 'Address not recorded' }}</p></div><div><h3>Employment</h3><p>Joined {{ $employee->joined_on->format('d F Y') }}</p><p>{{ $employee->user->active ? 'Active account' : 'Inactive account' }}</p><p>Monthly base salary <strong>{{ \App\Support\DecimalMoney::display($employee->base_salary) }}</strong></p></div></div>
<a class="btn btn-secondary" href="{{ route('employees.index') }}">All employee records</a>
@if(auth()->user()->isSuperAdmin())<section class="card"><h2>Delete employee profile</h2><p>Deletes this HR profile and its draft payroll entries, keeping the login account. Protected payroll history prevents deletion.</p><form method="post" action="{{ route('employees.destroy',$employee) }}" data-confirm="Delete this employee profile and its draft payroll entries?">@csrf @method('delete')<button class="btn-secondary">Delete employee profile</button></form></section>@endif
@endsection
