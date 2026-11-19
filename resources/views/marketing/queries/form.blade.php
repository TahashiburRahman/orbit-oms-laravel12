@extends('layouts.app')
@section('title',$query->exists ? 'Update client query' : 'Log a client query')
@section('content')
<div class="page-header"><h1>{{ $query->exists ? 'Update client query' : 'Log a client query' }}</h1><a href="{{ route('queries.index') }}">All queries</a></div>
<form class="card form-card" method="post" action="{{ $query->exists ? route('queries.update',$query) : route('queries.store') }}">@csrf @if($query->exists) @method('PUT') @endif
@php $fields=['subject'=>['Question subject','text',[],true],'priority'=>['Priority','select',['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'],true],'assigned_to'=>['Assign / escalate to','select',$assignees->mapWithKeys(fn($u)=>[$u->id=>$u->name.' · '.$u->team->name])->all(),true],'status'=>['Status','select',['open'=>'Open','awaiting_response'=>'Awaiting response','escalated'=>'Escalated','resolved'=>'Resolved'],true],'description'=>['Original client question','textarea',[],true]]; if(!$query->exists) $fields=['project_id'=>['Active project','select',$projects->pluck('name','id')->all(),true]]+$fields; @endphp
<div class="grid-form">@include('marketing.fields',['record'=>$query,'fields'=>$fields])</div><p class="muted">A query can be assigned to Marketing, Operations or a Developer. Reassignments and status changes remain in the activity history.</p><div class="form-actions"><button class="btn">Save query</button><a class="btn-secondary" href="{{ route('queries.index') }}">Cancel</a></div></form>
@endsection
