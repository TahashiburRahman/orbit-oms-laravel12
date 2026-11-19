@extends('layouts.app')
@section('title', $project->name)
@section('content')
<div class="page-header"><div><div class="eyebrow">Project</div><h1>{{ $project->name }}</h1><p class="muted">{{ $project->client->company ?: $project->client->name }}</p></div><div class="actions"><a class="btn-secondary" href="{{ route('projects.index') }}">All projects</a>@can('update', $project)<a class="btn" href="{{ route('projects.edit', $project) }}">Edit project</a>@endcan</div></div>
<div class="detail-grid">
    <section class="card"><div class="card-heading"><h2>Linked tasks</h2>@can('create', App\Models\Task::class)<a class="btn-secondary btn-sm" href="{{ route('tasks.create', ['project_id' => $project->id]) }}">Assign task</a>@endcan</div><div class="record-list">@forelse($tasks as $task)<a class="record-row" href="{{ route('tasks.show', $task) }}"><span><strong>{{ $task->title }}</strong><span class="record-subtitle">{{ $task->assignee?->name ?? 'Team queue' }} · {{ $task->due_date?->format('d M Y') ?? 'No deadline' }}</span></span><x-status :value="$task->status" /></a>@empty<div class="empty-state"><strong>No tasks linked yet</strong><p>Assigned project work will appear here.</p></div>@endforelse</div></section>
    <aside class="card"><h2>Project details</h2><dl class="detail-list"><dt>Status</dt><dd><x-status :value="$project->status" /></dd><dt>Deadline</dt><dd>{{ $project->due_date?->format('d M Y') ?? 'Not scheduled' }}</dd><dt>Owner</dt><dd>{{ $project->assignee?->name ?? 'Unassigned' }}</dd><dt>Team</dt><dd>{{ $project->team->name }}</dd><dt>Shared with</dt><dd>{{ $project->members->pluck('name')->join(', ') ?: 'No additional members' }}</dd></dl><div class="detail-section"><h3>Project notes</h3><p class="whitespace-pre-line muted">{{ $project->notes ?: 'No notes recorded.' }}</p></div></aside>
</div>
@include('partials.history')
<x-delete-record resource="projects" :record="$project" label="Delete project" />
@endsection
