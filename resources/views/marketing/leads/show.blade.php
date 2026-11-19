@extends('layouts.app')
@section('title',$lead->name)
@section('content')
<div class="page-header"><div><h1>{{ $lead->name }}</h1><p class="muted">{{ $lead->company }} · {{ $lead->source }}</p></div><div class="actions">@can('update',$lead)@unless($lead->converted_client_id)<a class="btn btn-secondary" href="{{ route('leads.edit',$lead) }}">Edit lead</a>@endunless @endcan<a href="{{ route('leads.index') }}">Pipeline</a></div></div>
@include('partials.deal-detail')
<div class="card form-card"><x-status :value="$lead->stage" /><dl class="detail-list"><dt>Contact</dt><dd>{{ $lead->email ?? 'No email' }} · {{ $lead->phone ?? 'No phone' }}</dd><dt>Assigned marketer</dt><dd>{{ $lead->assignee->name }}</dd><dt>Next follow up</dt><dd>{{ $lead->follow_up_at?->format('d M Y') ?? 'Not scheduled' }}</dd><dt>Notes</dt><dd class="whitespace-pre-line">{{ $lead->notes ?: 'No notes yet.' }}</dd></dl>
@if($lead->converted_client_id)<p>Converted {{ $lead->converted_at?->format('d M Y, H:i') }}. <a class="btn btn-secondary" href="{{ route('clients.show',$lead->converted_client_id) }}">Open client</a></p>@elseif($lead->stage==='qualified')@can('update',$lead)<form method="post" action="{{ route('leads.convert',$lead) }}">@csrf<button class="btn">Convert to client</button><p class="muted">Creates a linked client and preserves this lead history.</p></form>@endcan @endif</div>
@include('partials.history')
<x-delete-record resource="leads" :record="$lead" label="Delete lead" />
@endsection
