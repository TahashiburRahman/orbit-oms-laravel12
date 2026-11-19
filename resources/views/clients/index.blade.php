@extends('layouts.app')
@section('title', 'Clients')
@section('content')
<div class="page-header"><div><div class="eyebrow">{{ $clients->total() }} clients in this view</div><h1>Clients</h1><p class="muted">Contacts, relationships, and the work you deliver together.</p></div>@can('create', App\Models\Client::class)<a class="btn" href="{{ route('clients.create') }}">Add client</a>@endcan</div>
<form class="filter-bar" method="get" role="search">
    <input name="search" type="search" value="{{ request('search') }}" placeholder="Search name, company or email" aria-label="Search clients">
    <select name="status" aria-label="Filter client status"><option value="">All statuses</option>@foreach(['active', 'inactive'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
    <button class="btn-secondary">Apply filters</button>@if(request()->anyFilled(['search', 'status']))<a class="text-link" href="{{ route('clients.index') }}">Clear filters</a>@endif
</form>
<div class="card table-wrap"><table aria-label="Clients"><thead><tr><th scope="col">Client</th><th scope="col">Contact</th><th scope="col">Relationship owner</th><th scope="col">Status</th></tr></thead><tbody>
@forelse($clients as $client)
    <tr><td><a class="record-title" href="{{ route('clients.show', $client) }}">{{ $client->name }}</a><span class="record-subtitle">{{ $client->company ?: 'Company not recorded' }}</span></td><td>@if($client->email)<a href="mailto:{{ $client->email }}">{{ $client->email }}</a>@else<span class="muted">No email</span>@endif<span class="record-subtitle">{{ $client->phone ?: 'No phone' }}</span></td><td>{{ $client->assignee?->name ?? 'Unassigned' }}</td><td><x-status :value="$client->status" /></td></tr>
@empty
    <tr><td colspan="4"><div class="empty-state"><strong>No clients in this view</strong><p>{{ request()->anyFilled(['search', 'status']) ? 'Try another search or clear the filters.' : 'Clients assigned or shared with you will appear here.' }}</p></div></td></tr>
@endforelse
</tbody></table></div>{{ $clients->links() }}
@endsection
