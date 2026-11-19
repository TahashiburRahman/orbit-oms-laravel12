@extends('layouts.app')
@section('title','Teams')
@section('content')
<div class="page-header"><div><h1>Teams</h1><p class="muted">Manage team names and assign memberships through employee accounts. Workflow keys remain stable when a team is renamed.</p></div><a href="{{ route('users.index') }}" class="btn-secondary">People &amp; access</a></div>
@if(auth()->user()->isSuperAdmin())
<section class="card"><h2>Add team</h2><form class="grid-form" method="post" action="{{ route('teams.store') }}">@csrf
    <x-field id="new-team-name" name="name" label="Team name" required maxlength="80" />
    <x-field id="new-team-slug" name="slug" label="Workflow key" hint="Use lowercase letters, numbers, underscores or hyphens. This key cannot be changed later." required maxlength="60" pattern="[a-z][a-z0-9_-]*" />
    <div class="form-actions"><button class="btn">Create team</button></div>
</form></section>
@endif
@forelse($teams as $team)
<section class="card">
    <div class="card-heading"><div><h2>{{ $team->name }}</h2><p class="muted">{{ $team->slug }} &middot; {{ $team->members_count }} {{ $team->members_count === 1 ? 'membership' : 'memberships' }}</p></div></div>
    <form class="filter-bar" method="post" action="{{ route('teams.update',$team) }}">@csrf @method('put')
        <x-field :id="'team-name-'.$team->id" name="name" label="Team name" :value="$team->name" required maxlength="80" />
        <button class="btn-secondary">Save team name</button>
    </form>
    @if(auth()->user()->isSuperAdmin())
        <form method="post" action="{{ route('teams.destroy',$team) }}" data-confirm="Delete {{ $team->name }}? Teams linked to employees, work or software updates must be reassigned first.">@csrf @method('delete')<button class="btn-secondary text-danger">Delete team</button></form>
    @endif
</section>
@empty<div class="empty-state"><strong>No teams</strong><p>Create a team to organize work and employee dashboards.</p></div>@endforelse
@endsection
