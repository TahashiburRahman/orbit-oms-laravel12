@extends('layouts.app')
@section('title', 'Payroll · '.$run->period)
@section('content')
<div class="page-header"><div><p class="muted">MONTHLY PAYROLL · {{ strtoupper($run->status) }}</p><h1>{{ \Carbon\Carbon::createFromFormat('Y-m', $run->period)->format('F Y') }}</h1><p class="muted">{{ $run->entries->count() }} employees · {{ \App\Support\DecimalMoney::display($run->total()) }} total net pay</p></div><div class="actions"><a class="btn btn-secondary" href="{{ route('payroll.export', $run) }}">Export CSV</a>@if($run->status === 'draft')<a class="btn btn-secondary" href="{{ route('payroll.edit', $run) }}">Edit notes</a>@endif</div></div>
<div class="card form-card"><p>{{ $run->notes ?: 'No preparation notes.' }}</p><div class="actions">
@if($run->status === 'draft')<form method="post" action="{{ route('payroll.submit', $run) }}">@csrf<button class="btn">Submit for approval</button></form><span class="muted">Review all entries before submitting. Submission locks draft edits.</span>@endif
@if($run->status === 'submitted')<span class="muted">Awaiting Admin approval.</span>@if(auth()->user()->isAdmin())<form method="post" action="{{ route('payroll.approve', $run) }}">@csrf<button class="btn">Approve payroll</button></form>@endif @endif
@if($run->status === 'approved')<form method="post" action="{{ route('payroll.pay', $run) }}">@csrf<button class="btn">Record payroll as paid</button></form><span class="muted">Use after completing salary payments outside the application.</span>@endif
@if($run->status === 'paid')<span class="badge">Paid · {{ $run->paid_at->format('d M Y, h:i A') }}</span>@endif
</div></div>
@foreach($run->entries as $entry)
@php($effective = $entry->effective())
<section class="card form-card"><div class="page-header"><div><h2>{{ $entry->employee_name }}</h2><p class="muted">{{ $entry->employee_code }} · Net pay <strong>{{ \App\Support\DecimalMoney::display($effective->net_pay) }}</strong></p></div><a class="btn btn-secondary" href="{{ route('payroll.payslip', [$run, $entry]) }}">Printable payslip</a></div>
@if($run->status === 'draft')
<form method="post" action="{{ route('payroll.entries.update', [$run, $entry]) }}">@csrf @method('PATCH')
<div class="grid-form"><label class="field">Base salary (BDT)<input type="number" name="base_salary" required min="0" step="0.01" value="{{ $entry->base_salary }}"></label><label class="field">Allowances (BDT)<input type="number" name="allowances" required min="0" step="0.01" value="{{ $entry->allowances }}"></label><label class="field">Deductions (BDT)<input type="number" name="deductions" required min="0" step="0.01" value="{{ $entry->deductions }}"></label></div><div class="form-actions"><button class="btn btn-secondary">Save entry</button></div></form>
@else
<div class="grid-form"><p>Base salary<br><strong>{{ \App\Support\DecimalMoney::display($effective->base_salary) }}</strong></p><p>Allowances<br><strong>{{ \App\Support\DecimalMoney::display($effective->allowances) }}</strong></p><p>Deductions<br><strong>{{ \App\Support\DecimalMoney::display($effective->deductions) }}</strong></p></div>
@endif
@if($entry->corrections->isNotEmpty())<details><summary>Correction history ({{ $entry->corrections->count() }})</summary><p>Approved original: base {{ \App\Support\DecimalMoney::display($entry->base_salary) }}, allowances {{ \App\Support\DecimalMoney::display($entry->allowances) }}, deductions {{ \App\Support\DecimalMoney::display($entry->deductions) }}, net {{ \App\Support\DecimalMoney::display($entry->net_pay) }}.</p>@foreach($entry->corrections as $correction)<p><strong>{{ $correction->created_at->format('d M Y h:i A') }} · {{ $correction->author->name }}</strong><br>{{ $correction->reason }}<br>Corrected net pay: {{ \App\Support\DecimalMoney::display($correction->net_pay) }}</p>@endforeach</details>@endif
@if($run->status === 'approved' && auth()->user()->isAdmin())
<details><summary>Add an authorized correction</summary><p class="muted">The approved entry is retained. A new correction and reason are added to its history.</p><form method="post" action="{{ route('payroll.correct', [$run, $entry]) }}">@csrf<div class="grid-form"><label class="field">Corrected base (BDT)<input type="number" name="base_salary" required min="0" step="0.01" value="{{ $effective->base_salary }}"></label><label class="field">Corrected allowances (BDT)<input type="number" name="allowances" required min="0" step="0.01" value="{{ $effective->allowances }}"></label><label class="field">Corrected deductions (BDT)<input type="number" name="deductions" required min="0" step="0.01" value="{{ $effective->deductions }}"></label><label class="field">Reason for correction<textarea name="reason" required minlength="10" maxlength="3000" rows="3"></textarea></label></div><div class="form-actions"><button class="btn btn-secondary">Save correction</button></div></form></details>
@endif
</section>
@endforeach
<a class="btn btn-secondary" href="{{ route('payroll.index') }}">All payroll</a>
<x-delete-record resource="payroll" :record="$run" label="Delete draft payroll" />
@endsection
