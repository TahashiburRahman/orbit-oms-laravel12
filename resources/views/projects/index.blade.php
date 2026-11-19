@extends('layouts.app')
@section('title', 'Projects')
@section('content')
<div class="page-header"><div><div class="eyebrow">{{ $projects->total() }} projects in this view</div><h1>Projects</h1><p class="muted">Client work, team ownership, and upcoming deadlines.</p></div>@can('create', App\Models\Project::class)<a class="btn" href="{{ route('projects.create') }}">Add project</a>@endcan</div>
<form class="filter-bar" method="get" role="search"><input name="search" type="search" value="{{ request('search') }}" placeholder="Search projects" aria-label="Search projects"><select name="status" aria-label="Filter project status"><option value="">All statuses</option>@foreach(['planning', 'active', 'on_hold', 'completed', 'cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select><button class="btn-secondary">Apply filters</button>@if(request()->anyFilled(['search', 'status']))<a class="text-link" href="{{ route('projects.index') }}">Clear filters</a>@endif</form>
<div class="card table-wrap"><table aria-label="Projects"><thead><tr><th scope="col">Project</th><th scope="col">Client</th><th scope="col">Team and owner</th><th scope="col">Deadline</th><th scope="col">Status</th></tr></thead><tbody>
@forelse($projects as $project)
    <tr><td><a class="record-title" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></td><td>{{ $project->client->company ?: $project->client->name }}</td><td>{{ $project->team->name }}<span class="record-subtitle">{{ $project->assignee?->name ?? 'Unassigned' }}</span></td><td class="nowrap {{ $project->due_date?->isBefore(today()) && !in_array($project->status, ['completed', 'cancelled']) ? 'text-danger' : '' }}">{{ $project->due_date?->format('d M Y') ?? 'Not scheduled' }}</td><td><x-status :value="$project->status" /></td></tr>
@empty
    <tr><td colspan="5"><div class="empty-state"><strong>No projects in this view</strong><p>{{ request()->anyFilled(['search', 'status']) ? 'Try another search or clear the filters.' : 'Your team projects and shared work will appear here.' }}</p></div></td></tr>
@endforelse
</tbody></table></div>{{ $projects->links() }}
@endsection
