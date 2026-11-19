@extends('layouts.app')
@section('title',ucfirst($team).' work update')
@section('content')
<div class="page-header"><div><h1>{{ ucfirst($team) }} work update</h1><p class="muted">{{ $task->exists?$task->title:'Record your daily work.' }}</p></div><a class="btn-secondary" href="{{ route('tasks.index') }}">Back to tasks</a></div>
@if($task->exists && !$task->structured_fields_required)<div class="alert alert-success">Legacy record: historical fields may remain blank. Existing links and workflow history are preserved.</div>@endif
@if($task->isDevelopment() && $task->exists)<div class="card"><p>Completed technical work is submitted to Operations for verification. It is shown as completed only after verification. Actions supply missing configuration and handover notes; existing technical notes are preserved.</p><p>Current workflow: {{ ucfirst(str_replace('_',' ',$task->status)) }}</p></div>@endif
<form class="card grid-form" method="post" action="{{ $task->exists?route('work.update',$task):route('work.store',$team) }}">@csrf @if($task->exists)@method('put')@endif
@if($team==='developer')
<x-field name="work_date" label="Date" type="date" :value="($task->reported_at??now())->format('Y-m-d')" :required="$task->structured_fields_required" />
<x-field name="work_time" label="Time (Asia/Dhaka)" type="time" :value="($task->reported_at??now())->format('H:i')" :required="$task->structured_fields_required" />
<div class="field"><label for="reporter-name">Name</label><input id="reporter-name" value="{{ auth()->user()->name }}" readonly></div>
@endif
<x-client-entry :task="$task" :domain-required="$task->structured_fields_required" />
@if($team==='developer')<x-field name="classification" label="Task classification" :value="$task->classification" :options="array_combine(App\Models\Task::CLASSIFICATIONS,App\Models\Task::CLASSIFICATIONS)" :required="$task->structured_fields_required" />@endif
<x-field class="full-width" name="description" label="Task details" type="textarea" :value="$task->description" :required="$task->structured_fields_required" />
<x-field class="full-width" name="actions" label="Actions" type="textarea" :value="$task->actions" />
<x-field name="work_status" label="Status" :value="$task->exists?$task->work_status:'pending'" :options="$task->work_status_options" required />
<div class="form-actions"><button class="btn">Save work update</button></div></form>
@endsection
