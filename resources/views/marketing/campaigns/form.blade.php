@extends('layouts.app')
@section('title',$campaign->exists ? 'Edit campaign' : 'New campaign')
@section('content')
<div class="page-header"><h1>{{ $campaign->exists ? 'Edit campaign' : 'New campaign' }}</h1><a href="{{ route('campaigns.index') }}">All campaigns</a></div>
<form class="card form-card" method="post" action="{{ $campaign->exists ? route('campaigns.update',$campaign) : route('campaigns.store') }}">@csrf @if($campaign->exists) @method('PUT') @endif
<div class="grid-form">@include('marketing.fields',['record'=>$campaign,'fields'=>['name'=>['Campaign name','text',[],true],'status'=>['Status','select',['planning'=>'Planning','active'=>'Active','paused'=>'Paused','completed'=>'Completed'],true],'assigned_to'=>['Owner','select',$marketers->pluck('name','id')->all(),true],'starts_at'=>['Start date','date'],'ends_at'=>['End date','date'],'description'=>['Campaign objective and audience','textarea']]])</div><div class="form-actions"><button class="btn">Save campaign</button><a href="{{ route('campaigns.index') }}">Cancel</a></div></form>
@endsection
