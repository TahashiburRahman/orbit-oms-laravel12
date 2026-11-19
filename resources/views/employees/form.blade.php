@extends('layouts.app')
@section('title', $employee->exists ? 'Edit employee record' : 'New employee record')
@section('content')
<div class="page-header"><div><div class="eyebrow">People and payroll</div><h1>{{ $employee->exists ? 'Edit employee record' : 'New employee record' }}</h1><p class="muted">Employment information used to prepare monthly payroll.</p></div><a class="btn-secondary" href="{{ route('employees.index') }}">Employee records</a></div>
<form class="card form-card" method="post" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">@csrf @if($employee->exists) @method('PUT') @endif
<div class="grid-form">
    <x-field name="user_id" label="Employee account" :value="$employee->user_id" :options="$users->mapWithKeys(fn($user) => [$user->id => $user->name.' · '.$user->email])" required :disabled="$employee->exists" />
    <x-field name="employee_code" label="Employee code" :value="$employee->employee_code" required maxlength="40" placeholder="Enter employee code" />
    <x-field name="job_title" label="Job title" :value="$employee->job_title" required maxlength="255" />
    <x-field name="joined_on" label="Joined on" type="date" :value="$employee->joined_on?->format('Y-m-d')" required />
    <x-field name="phone" label="Phone" type="tel" :value="$employee->phone" maxlength="40" placeholder="+880" />
    <x-field name="base_salary" label="Monthly base salary (BDT)" type="number" :value="$employee->base_salary ?? '0.00'" min="0" max="9999999999.99" step="0.01" required />
    <x-field name="address" label="Address" type="textarea" :value="$employee->address" maxlength="3000" class="full-width" />
</div>
<div class="form-actions"><button class="btn">Save employee record</button><a class="btn-secondary" href="{{ route('employees.index') }}">Cancel</a></div>
</form>
@endsection
