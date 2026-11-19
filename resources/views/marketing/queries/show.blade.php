@extends('layouts.app')
@section('title',$query->subject)
@section('content')
<div class="page-header"><div><h1>{{ $query->subject }}</h1><p class="muted">{{ $query->project->client->name }} · {{ $query->project->name }}</p></div><div class="actions">@can('update',$query)<a class="btn btn-secondary" href="{{ route('queries.edit',$query) }}">Update / escalate</a>@endcan<a href="{{ route('queries.index') }}">All queries</a></div></div>
<div class="card form-card"><x-status :value="$query->status" /><span class="badge">{{ $query->priority }}</span><p>Logged by {{ $query->requester->name }} · Assigned to {{ $query->assignee->name }}</p><p class="whitespace-pre-line">{{ $query->description }}</p></div>
<div class="card form-card"><h2>Conversation</h2>@forelse($query->messages as $message)<article class="activity-item"><strong>{{ $message->author->name }}</strong><span class="muted"> · {{ $message->created_at->format('d M Y, H:i') }}</span><p class="whitespace-pre-line">{{ $message->body }}</p></article>@empty<p class="muted">No responses yet.</p>@endforelse</div>
@can('update',$query)<form class="card form-card" method="post" action="{{ route('queries.messages.store',$query) }}">@csrf<div class="field"><label for="body">Add a response</label><textarea id="body" name="body" rows="4" required>{{ old('body') }}</textarea></div><button class="btn">Add response</button></form>@endcan
@include('marketing.activity')
<x-delete-record resource="queries" :record="$query" label="Delete query" />
@endsection
