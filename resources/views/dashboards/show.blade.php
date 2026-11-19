@extends('layouts.app')
@section('title', 'Overview')
@section('content')
<div class="page-header dashboard-heading">
    <div>
        <div class="eyebrow">{{ $dashboardTeamName }} workspace</div>
        <h1>{{ now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening') }}, {{ explode(' ', auth()->user()->name)[0] }}.</h1>
        <p>Your work, priorities and progress in one place.</p>
    </div>
    <div class="dashboard-heading-actions">
        <span class="dashboard-date"><time datetime="{{ now()->toDateString() }}">{{ now()->format('l, j F Y') }}</time></span>
        @can('create', \App\Models\Task::class)
            <a class="btn" href="{{ route('tasks.create') }}">Create task</a>
        @else
            <a class="btn" href="{{ route('tasks.index', ['mine' => 1]) }}">My tasks</a>
        @endcan
    </div>
</div>

@include('partials.work-actions')
<section class="dashboard-metrics" aria-label="Workspace metrics">
    @foreach($stats as $stat)
        <a class="dashboard-metric" href="{{ route($stat['route'], $stat['query']) }}">
            <span class="dashboard-metric-label">{{ $stat['label'] }}</span>
            <strong class="dashboard-metric-value metric-{{ $stat['tone'] }}">{{ number_format($stat['value']) }}</strong>
            <span class="dashboard-metric-note">{{ $stat['note'] }}</span>
        </a>
    @endforeach
</section>

<div class="overview-grid">
    <section class="card overview-task-panel">
        <div class="card-heading">
            <div><h2>Your task board</h2><p class="muted">Upcoming work, ordered by due date.</p></div>
            <a class="text-link" href="{{ route('tasks.index') }}">View all tasks</a>
        </div>
        <div class="overview-task-tabs" aria-label="Task views">
            <span class="overview-task-tab current">Priority queue <span>{{ $stats[0]['value'] }}</span></span>
            <a class="overview-task-tab" href="{{ route('tasks.index', ['mine' => 1]) }}">Assigned to me</a>
            <a class="overview-task-tab" href="{{ route('tasks.index', ['overdue' => 1]) }}">Overdue</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Task</th><th scope="col">Status</th><th scope="col">Due date</th></tr></thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr>
                            <td>
                                <a class="record-title" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                <span class="record-subtitle">{{ $task->assignee?->name ?? 'Unassigned' }} <span aria-hidden="true">&middot;</span> {{ ucfirst(str_replace('_', ' ', $task->work_type)) }}</span>
                            </td>
                            <td><span class="badge status-{{ $task->status }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span></td>
                            <td class="overview-due-cell">
                                @if($task->due_date)
                                    <time class="{{ $task->due_date->isBefore(today()) ? 'text-danger' : 'muted' }}" datetime="{{ $task->due_date->toDateString() }}">{{ $task->due_date->format('d M Y') }}</time>
                                    @if($task->due_date->isBefore(today()))<small class="overview-overdue">Overdue</small>@endif
                                @else
                                    <span class="muted">No due date</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state"><strong>Your queue is clear</strong><p>Active tasks will appear here when work is assigned.</p><a class="text-link" href="{{ route('tasks.index') }}">Browse tasks</a></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <aside class="overview-insights">
        <section class="card overview-progress">
            <div class="card-heading"><h2>Work progress</h2><span class="overview-period">All tasks</span></div>
            <div class="overview-completion"><strong>{{ $completionRate }}<span>%</span></strong><span>completed<br><small>{{ number_format($finishedTasks) }} of {{ number_format($taskTotal) }} tasks</small></span></div>
            @if($taskTotal)
                <div class="overview-stacked-bar" aria-hidden="true">
                    @foreach($taskStatuses as $taskStatus)
                        @if($taskStatus['count'])<span class="progress-{{ $taskStatus['status'] }}" style="width: {{ $taskStatus['percentage'] }}%"></span>@endif
                    @endforeach
                </div>
                <dl class="overview-status-list">
                    @foreach($taskStatuses as $taskStatus)
                        <div><dt><span class="overview-status-dot progress-{{ $taskStatus['status'] }}" aria-hidden="true"></span>{{ $taskStatus['label'] }}</dt><dd>{{ number_format($taskStatus['count']) }}</dd></div>
                    @endforeach
                </dl>
            @else
                <p class="overview-chart-empty muted">Task progress will appear when your workspace has tasks.</p>
            @endif
        </section>
        <section class="overview-team-focus">
            <div class="eyebrow">Team focus</div>
            <div class="overview-focus-total"><strong>{{ number_format($special['value']) }}</strong><h2>{{ $special['label'] }}</h2></div>
            <p>{{ $special['description'] }}</p>
            @if(Route::has($special['route']))<a class="text-link" href="{{ route($special['route']) }}">{{ $special['action'] }}</a>@endif
        </section>
    </aside>

    <section class="card overview-projects">
        <div class="card-heading"><h2>Recent projects</h2><a class="text-link" href="{{ route('projects.index') }}">View projects</a></div>
        <div class="overview-project-list">
            @forelse($projects as $project)
                <a class="overview-project-row" href="{{ route('projects.show', $project) }}">
                    <span class="overview-project-monogram" aria-hidden="true">{{ mb_strtoupper(mb_substr($project->name, 0, 1)) }}</span>
                    <span class="overview-project-info"><strong>{{ $project->name }}</strong><small>{{ $project->due_date ? 'Due '.$project->due_date->format('d M Y') : 'No due date' }}</small></span>
                    <span class="badge status-{{ $project->status }}">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
                </a>
            @empty
                <div class="empty-state"><strong>No projects yet</strong><p>Projects shared with you will appear here.</p></div>
            @endforelse
        </div>
    </section>

    <section class="card overview-updates">
        <div class="card-heading"><h2>Software updates</h2><a class="text-link" href="{{ route('releases.index') }}">View updates</a></div>
        <div class="overview-release-list">
            @forelse($releases as $release)
                <a class="overview-release-row" href="{{ route('releases.show', $release) }}">
                    <div class="overview-release-meta"><span class="version-tag">{{ $release->version }}</span><time datetime="{{ $release->publish_at->toDateString() }}">{{ $release->publish_at->format('d M Y') }}</time></div>
                    <strong>{{ $release->summary }}</strong>
                    <p>{{ \Illuminate\Support\Str::limit($release->details, 105) }}</p>
                </a>
            @empty
                <div class="empty-state"><strong>You are up to date</strong><p>Published updates for your team will appear here.</p></div>
            @endforelse
        </div>
    </section>
</div>
@endsection
