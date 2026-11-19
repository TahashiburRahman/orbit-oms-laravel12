@extends('layouts.app')
@section('title',$brief->exists ? 'Edit creative brief' : 'New creative brief')
@section('content')
<div class="page-header"><h1>{{ $brief->exists ? 'Edit creative brief' : 'New creative brief' }}</h1><a href="{{ route('briefs.index') }}">Creative studio</a></div>
@php if (!$brief->exists) $brief->campaign_id = request('campaign_id'); @endphp
<form class="card form-card" method="post" action="{{ $brief->exists ? route('briefs.update',$brief) : route('briefs.store') }}">@csrf @if($brief->exists) @method('PUT') @endif
<div class="grid-form">@include('marketing.fields',['record'=>$brief,'fields'=>['title'=>['Brief title','text',[],true],'type'=>['Deliverable type','select',['graphic'=>'Graphic / artwork','software_video'=>'Software marketing video'],true],'campaign_id'=>['Linked campaign','select',[''=>'No campaign']+$campaigns->pluck('name','id')->all()],'assigned_to'=>['Assign designer','select',[''=>'Available for Graphics to claim']+$graphics->pluck('name','id')->all()],'due_date'=>['Due date','date'],'description'=>['Audience, requirements and acceptance criteria','textarea',[],true]]])</div><div class="form-actions"><button class="btn">Save brief</button><a class="btn-secondary" href="{{ route('briefs.index') }}">Cancel</a></div></form>
@endsection
