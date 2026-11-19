@extends('layouts.app')
@section('title',$lead->exists ? 'Edit lead' : 'Add lead')
@section('content')
<div class="page-header"><h1>{{ $lead->exists ? 'Edit lead' : 'Add lead' }}</h1><a href="{{ route('leads.index') }}" class="btn btn-secondary">Back to pipeline</a></div>
<form class="card form-card" method="post" action="{{ $lead->exists ? route('leads.update',$lead) : route('leads.store') }}">@csrf @if($lead->exists) @method('PUT') @endif
<div class="grid-form">@include('marketing.fields',['record'=>$lead,'fields'=>[
    'name'=>['Contact name','text',[],true],'company'=>['Company','text'],'email'=>['Email','email'],'phone'=>['Phone','tel'],
    'source'=>['Lead source','text',[],true],'stage'=>['Stage','select',array_combine(['new','contacted','qualified','lost'],['New','Contacted','Qualified','Lost']),true],
    'assigned_to'=>['Assigned marketer','select',$marketers->pluck('name','id')->all(),true],'follow_up_at'=>['Next follow up','date'],'notes'=>['Notes','textarea']
]])</div><div class="form-actions"><button class="btn">Save lead</button><a href="{{ route('leads.index') }}">Cancel</a></div></form>
@endsection
