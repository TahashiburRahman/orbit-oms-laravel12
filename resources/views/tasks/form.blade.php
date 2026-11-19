@extends('layouts.app') @section('title',$task->exists?'Update task':'Add task') @section('content')
<div class="page-header"><div><h1>{{ $task->exists?'Update task':'Add task' }}</h1><p class="muted">{{ $task->isDevelopment() ? 'Document requirements, progress, and the handover for verification.' : (auth()->user()->inTeam('admin','operations') ? 'Choose an owner, add the requirements, and set a due date.' : 'Record your work and add a client or choose an existing suggestion.') }}</p></div><a class="btn-secondary" href="{{ route('tasks.index') }}">Back to tasks</a></div>
<form class="card grid-form" action="{{ $task->exists?route('tasks.update',$task):route('tasks.store') }}" method="post">@csrf @if($task->exists)@method('put')@endif
@if(auth()->user()->inTeam('admin','operations'))
<x-field name="title" label="Task title" :value="$task->title" required /><x-field name="work_type" label="Work type" :value="$task->work_type" :options="['general'=>'General task','domain'=>'Domain setup','pixel'=>'Tracking pixel setup','courier'=>'Courier integration','landing_page'=>'Landing page']" required />
<x-client-entry :task="$task" /><x-field name="project_id" label="Project" :value="$task->project_id??request('project_id')" :options="$projects->pluck('name','id')" />
<x-field name="team_id" label="Assigned team" hint="Setup requests are assigned to Developer." :value="$task->team_id??($task->work_type!=='general'?$teams->firstWhere('slug','developer')?->id:null)" :options="$teams->pluck('name','id')" required />
<x-field name="assigned_to" label="Assigned employee" :value="$task->assigned_to" :options="$users->mapWithKeys(fn($u)=>[$u->id=>$u->name.' · '.$u->team?->name])" />
<x-field name="priority" label="Priority" :value="$task->priority" :options="['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent']" required /><x-field name="due_date" label="Due date" type="date" :value="$task->due_date?->format('Y-m-d')" />
<x-field class="full-width" name="description" label="Description / requirements" type="textarea" :value="$task->description" />
@elseif(!$task->exists)
<x-field name="title" label="Task title" required /><x-client-entry :task="$task" />
<x-field name="project_id" label="Project (optional)" :options="$projects->pluck('name','id')" />
<x-field name="priority" label="Priority" value="normal" :options="['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent']" required />
<x-field name="due_date" label="Due date" type="date" /><x-field class="full-width" name="description" label="Task details" type="textarea" />
<p class="muted full-width">This general task is assigned to you in your primary team.</p>
@else<div class="full-width"><h2>{{ $task->title }}</h2><p class="muted">{{ $task->description }}</p></div>@endif
<x-field name="status" label="Status" :value="$task->status" :options="$task->isDevelopment()?['open'=>'Open','in_progress'=>'In progress','blocked'=>'Blocked','submitted'=>'Submitted to Operations','verified'=>'Verified and closed']:['open'=>'Open','in_progress'=>'In progress','blocked'=>'Blocked','completed'=>'Completed']" required />
<x-field name="blocker" label="Blocker" hint="Required when the task is blocked." type="textarea" :value="$task->blocker" />
@if($task->isDevelopment())<x-field class="full-width" name="configuration_summary" label="Configuration summary" type="textarea" :value="$task->configuration_summary" /><x-field class="full-width" name="handover_notes" label="Handover notes" type="textarea" :value="$task->handover_notes" />@if(auth()->user()->inTeam('admin','operations'))<x-field class="full-width" name="verification_results" label="Operations verification results" type="textarea" :value="$task->verification_results" />@endif @endif
<div class="form-actions"><button class="btn">Save task</button></div></form>@endsection
