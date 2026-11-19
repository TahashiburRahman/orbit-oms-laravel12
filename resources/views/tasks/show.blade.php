@extends('layouts.app')
@section('title', $task->title)
@section('content')
<div class="page-header"><div><div class="eyebrow">{{ ucfirst(str_replace('_', ' ', $task->work_type)) }}</div><h1>{{ $task->title }}</h1></div><div class="actions"><a class="btn-secondary" href="{{ route($task->isDevelopment() ? 'development.index' : 'tasks.index') }}">Back to work</a>@can('update', $task)<a class="btn" href="{{ route('tasks.edit', $task) }}">Update work</a>@endcan</div></div>
@include('partials.work-detail')
@if($task->record_type==='legacy' && ($task->domain_name || $task->website_name))<section class="card"><h2>Website</h2><p>{{ $task->website_name }} {{ $task->domain_name }}</p></section>@endif
<div class="detail-grid">
    <section class="card"><h2>Work details</h2><p class="whitespace-pre-line">{{ $task->description ?: 'No description provided.' }}</p>
        @foreach(['blocker' => 'Blocker', 'configuration_summary' => 'Configuration summary', 'handover_notes' => 'Handover notes', 'verification_results' => 'Verification results'] as $key => $label)
            @if($task->$key)<div class="detail-section"><h3>{{ $label }}</h3><p class="whitespace-pre-line">{{ $task->$key }}</p></div>@endif
        @endforeach
    </section>
    <aside class="card"><h2>Assignment</h2><dl class="detail-list"><dt>Status</dt><dd><x-status :value="$task->status" /></dd><dt>Priority</dt><dd><x-status :value="$task->priority" /></dd><dt>Assigned to</dt><dd>{{ $task->assignee?->name ?? 'Team queue' }}</dd><dt>Team</dt><dd>{{ $task->team->name }}</dd><dt>Requested by</dt><dd>{{ $task->creator->name }}</dd><dt>Due date</dt><dd class="{{ $task->due_date?->isBefore(today()) && !in_array($task->status, ['verified', 'completed']) ? 'text-danger' : '' }}">{{ $task->due_date?->format('d M Y') ?? 'Not scheduled' }}</dd><dt>Client</dt><dd>{{ $task->client?->name ?? 'No linked client' }}</dd><dt>Project</dt><dd>{{ $task->project?->name ?? 'No linked project' }}</dd>@if($task->verified_at)<dt>Verified</dt><dd>{{ $task->verified_at->format('d M Y, h:i A') }}</dd>@endif</dl></aside>
</div>
<section class="card"><h2>Conversation</h2>@forelse($comments as $comment)<article class="activity-item"><strong>{{ $comment->user->name }}</strong> <time class="muted">{{ $comment->created_at->format('d M, h:i A') }}</time><p class="whitespace-pre-line">{{ $comment->body }}</p></article>@empty<p class="muted">Share a question or update with everyone working on this task.</p>@endforelse<form method="post" action="{{ route('tasks.comments', $task) }}">@csrf<x-field name="body" label="Add comment" type="textarea" required maxlength="10000" /><div class="form-actions"><button class="btn-secondary">Post comment</button></div></form></section>
@include('partials.history')
@if(auth()->user()->inTeam('admin','operations'))@can('update',$task)<section class="card"><h2>Manage assignment</h2><form method="post" action="{{ route('tasks.assign',$task) }}">@csrf @method('put')<x-field name="assigned_to" label="Assigned employee" :value="$task->assigned_to" :options="$assignmentUsers->pluck('name','id')" /><div class="form-actions"><button class="btn-secondary">Update assignment</button></div></form></section>@endcan @endif
<x-delete-record resource="tasks" :record="$task" label="Delete task" />
@endsection
