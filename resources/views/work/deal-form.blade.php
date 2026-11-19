@extends('layouts.app')
@section('title',$kind==='referral'?'Bring a Client':'Marketing update')
@section('content')
<div class="page-header"><div><h1>{{ $kind==='referral'?'Bring a Client':'Marketing update' }}</h1><p class="muted">Deal decisions and lead qualification remain separate. Approval does not automatically create a Client.</p></div><a class="btn-secondary" href="{{ route($kind.'.index') }}">{{ $kind==='referral'?'My referrals':'Marketing updates' }}</a></div>
<form class="card grid-form" method="post" action="{{ $lead->exists?route('deals.update',$lead):route($kind.'.store') }}">@csrf @if($lead->exists)@method('put')@endif
<x-field name="name" label="Client name" :value="$lead->name" required />
<x-field name="domain_name" label="Domain link/name (optional)" :value="$lead->domain_name" placeholder="example.com or https://example.com" />
@php($mayDecide=auth()->user()->isAdmin()||($lead->exists?$lead->canEdit(auth()->user()):auth()->user()->inTeam('marketing')))
@if($mayDecide)<x-field name="deal_status" label="Status" :value="$lead->deal_status??'in_progress'" :options="App\Models\Lead::DEAL_STATUSES" required />
@else<input type="hidden" name="deal_status" value="in_progress"><div class="field"><label>Status</label><p>In Progress</p><small>Authorized Marketing staff or Admin handles deal decisions.</small></div>@endif
<div class="form-actions"><button class="btn">{{ $lead->exists?'Save changes':'Submit' }}</button></div></form>
@endsection
