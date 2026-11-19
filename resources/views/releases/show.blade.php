@extends('layouts.app')
@section('title', 'Software update')
@section('content')
<div class="page-header"><div><div class="eyebrow">Software update · {{ $release->version }}</div><h1>{{ $release->summary }}</h1><p>{{ $release->publish_at?->format('d M Y, H:i') ?? 'No publication date' }} · Asia/Dhaka</p></div><div class="actions">@can('update', $release)<a class="btn" href="{{ route('releases.edit', $release) }}">Edit update</a>@endcan<a class="btn btn-secondary" href="{{ route('releases.index') }}">All updates</a></div></div><article class="card form-card"><div class="actions"><span class="badge">{{ $release->status === 'published' && $release->publish_at?->isFuture() ? 'Scheduled' : ucfirst($release->status) }}</span>@foreach($release->targetTeams as $team)<span class="badge badge-neutral">{{ $team->name }}</span>@endforeach</div><div class="prose-content">{{ $release->details }}</div></article>
<x-delete-record resource="releases" :record="$release" label="Delete software update" />
@endsection
