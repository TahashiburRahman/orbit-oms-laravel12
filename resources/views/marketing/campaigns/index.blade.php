@extends('layouts.app')
@section('title','Marketing campaigns')
@section('content')
<div class="page-header"><div><h1>Marketing campaigns</h1><p class="muted">Plan software launches and keep the content calendar connected.</p></div>@can('create',\App\Models\Campaign::class)<a class="btn" href="{{ route('campaigns.create') }}">New campaign</a>@endcan</div>
<form class="filter-bar" method="get" role="search"><input name="q" aria-label="Search campaigns" placeholder="Search campaigns" value="{{ request('q') }}"><button class="btn btn-secondary">Search</button></form>
<div class="card table-wrap"><table aria-label="Campaign posts"><thead><tr><th scope="col">Campaign</th><th scope="col">Status</th><th scope="col">Period</th><th scope="col">Posts</th><th scope="col">Creative briefs</th></tr></thead><tbody>@forelse($campaigns as $campaign)<tr><td><a href="{{ route('campaigns.show',$campaign) }}">{{ $campaign->name }}</a></td><td><x-status :value="$campaign->status" /></td><td>{{ $campaign->starts_at?->format('d M') ?? 'Unscheduled' }} to {{ $campaign->ends_at?->format('d M Y') ?? 'Open' }}</td><td>{{ $campaign->posts_count }}</td><td>{{ $campaign->briefs_count }}</td></tr>@empty<tr><td colspan="5" class="empty-state">No campaigns yet.</td></tr>@endforelse</tbody></table></div>{{ $campaigns->links() }}
@endsection
