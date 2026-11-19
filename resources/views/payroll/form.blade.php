@extends('layouts.app')
@section('title', $run->exists ? 'Edit payroll notes' : 'Prepare payroll')
@section('content')
<div class="page-header"><div><div class="eyebrow">Monthly payroll</div><h1>{{ $run->exists ? 'Edit payroll notes' : 'Prepare payroll' }}</h1><p class="muted">{{ $run->exists ? 'Employee identity and payroll period are preserved.' : 'Create a draft using the current base salary for each active employee.' }}</p></div><a class="btn-secondary" href="{{ route('payroll.index') }}">All payroll</a></div>
<form class="card form-card" method="post" action="{{ $run->exists ? route('payroll.update', $run) : route('payroll.store') }}">@csrf @if($run->exists) @method('PUT') @endif
<div class="grid-form">
    <x-field name="period" label="Payroll period" type="month" :value="$run->period ?? now()->format('Y-m')" required :disabled="$run->exists" />
    <x-field name="notes" label="Preparation notes" type="textarea" :value="$run->notes" maxlength="5000" />
</div>
<div class="form-actions"><button class="btn">{{ $run->exists ? 'Save notes' : 'Create draft payroll' }}</button><a class="btn-secondary" href="{{ route('payroll.index') }}">Cancel</a></div>
</form>
@endsection
