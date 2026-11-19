@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
<div class="page-header"><div><p class="muted">PEOPLE & PAYROLL</p><h1>Monthly payroll</h1><p class="muted">Prepare, review, approve, and record payroll in BDT.</p></div><a class="btn" href="{{ route('payroll.create') }}">Prepare payroll</a></div>
<div class="card table-wrap"><table aria-label="Payroll"><thead><tr><th scope="col">Period</th><th scope="col">Employees</th><th scope="col">Status</th><th scope="col">Approved</th><th scope="col">Payment recorded</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
@forelse($runs as $run)<tr><td><a href="{{ route('payroll.show', $run) }}">{{ \Carbon\Carbon::createFromFormat('Y-m', $run->period)->format('F Y') }}</a></td><td>{{ $run->entries_count }}</td><td><x-status :value="$run->status" /></td><td>{{ $run->approved_at?->format('d M Y') ?? 'Pending' }}</td><td>{{ $run->paid_at?->format('d M Y') ?? 'Not recorded' }}</td><td><a href="{{ route('payroll.show', $run) }}">Review run</a></td></tr>
@empty<tr><td colspan="6" class="empty-state">No payroll runs yet. Add employee records, then prepare your first month.</td></tr>@endforelse
</tbody></table>{{ $runs->links() }}</div>
@endsection
