@extends('layouts.app')
@section('title', $development ? 'Development requests' : 'Tasks')
@section('content')
@php($availableWorkTeams=App\Models\Team::whereIn('slug',['developer','operations','marketing'])->pluck('slug')->all())
<div class="page-header">
    <div><div class="eyebrow">{{ $tasks->total() }} {{ $development ? 'requests' : 'tasks' }} in this view</div><h1>{{ $development ? 'Development requests' : 'Tasks' }}</h1><p class="muted">{{ $development ? 'Track setup, delivery, and Operations verification.' : 'Assignments, deadlines, and the next step for every task.' }}</p></div>
    @if(!$development || (in_array('developer',$availableWorkTeams,true)&&auth()->user()->inTeam('admin','operations')))@can('create', App\Models\Task::class)<a class="btn" href="{{ route('tasks.create', ['type' => $development ? 'domain' : 'general']) }}">{{ $development ? 'New setup request' : 'Add task' }}</a>@endcan @endif
</div>
<form class="filter-bar" method="get" role="search">
    <select name="record_type" aria-label="Record type"><option value="">All record types</option>@foreach(['developer'=>'Developer','operations'=>'Operations','legacy'=>'Legacy / other'] as $key=>$label)<option value="{{ $key }}" @selected(request('record_type')===$key)>{{ $label }}</option>@endforeach</select>
    <select name="classification" aria-label="Developer classification"><option value="">All Developer classifications</option>@foreach(App\Models\Task::CLASSIFICATIONS as $classification)<option @selected(request('classification')===$classification)>{{ $classification }}</option>@endforeach</select>
    <input name="search" type="search" value="{{ request('search') }}" placeholder="Search work" aria-label="Search work">
    <select name="status" aria-label="Filter task status"><option value="">All statuses</option>@foreach(['pending', 'in_progress', 'completed', 'blocked', 'submitted', 'verified', 'open'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select>
    <label class="checkbox-field"><input type="checkbox" name="mine" value="1" @checked(request('mine'))> Assigned to me</label>
    <label class="checkbox-field"><input type="checkbox" name="overdue" value="1" @checked(request('overdue'))> Overdue</label>
    <button class="btn-secondary">Apply filters</button>
    @if(request()->anyFilled(['search', 'status', 'mine', 'overdue']))<a class="text-link" href="{{ route($development ? 'development.index' : 'tasks.index') }}">Clear filters</a>@endif
</form>
<div class="actions">@foreach(['developer','operations'] as $team)@if(in_array($team,$availableWorkTeams,true)&&auth()->user()->inTeam($team))<a class="btn-secondary" href="{{ route('work.create',$team) }}">Add {{ ucfirst($team) }} update</a>@endif @endforeach @if(in_array('marketing',$availableWorkTeams,true)&&auth()->user()->inTeam('marketing'))<a class="btn-secondary" href="{{ route('marketing_update.create') }}">Add Marketing update</a>@endif</div>
<div class="card table-wrap">
    <table aria-label="{{ $development ? 'Development requests' : 'Tasks' }}">
        <thead><tr><th scope="col">Task</th><th scope="col">Assigned to</th><th scope="col">Priority</th><th scope="col">Due date</th><th scope="col">Status</th></tr></thead>
        <tbody>
            @forelse($tasks as $task)
                <tr>
                    <td><a class="record-title" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>@include('partials.work-summary',['task'=>$task])<span class="record-subtitle">{{ ucfirst(str_replace('_', ' ', $task->work_type)) }} · {{ $task->project?->name ?? 'No project' }}</span></td>
                    <td>{{ $task->assignee?->name ?? 'Team queue' }}<span class="record-subtitle">{{ $task->team->name }}</span></td>
                    <td><x-status :value="$task->priority" /></td>
                    <td class="nowrap {{ $task->due_date?->isBefore(today()) && !in_array($task->status, ['verified', 'completed']) ? 'text-danger' : '' }}">{{ $task->due_date?->format('d M Y') ?? 'Not scheduled' }}</td>
                    <td><x-status :value="$task->status" /></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><strong>No tasks in this view</strong><p>{{ request()->anyFilled(['search', 'status', 'mine', 'overdue']) ? 'Adjust your filters to find other work.' : 'Assignments will appear here when work is created for your team.' }}</p></div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $tasks->links() }}
@endsection
