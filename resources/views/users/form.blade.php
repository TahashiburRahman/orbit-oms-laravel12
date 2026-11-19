@extends('layouts.app') @section('title',$user->exists?'Edit employee':'Add employee') @section('content')
<div class="page-header"><h1>{{ $user->exists?'Edit employee':'Add employee' }}</h1><a class="btn-secondary" href="{{ route('users.index') }}">Back to people</a></div>
<form class="card grid-form" method="post" action="{{ $user->exists?route('users.update',$user):route('users.store') }}">@csrf @if($user->exists)@method('put')@endif
<input type="hidden" name="memberships_present" value="1">
<x-field name="name" label="Full name" :value="$user->name" required />
<x-field name="email" label="Work email" type="email" :value="$user->email" required />
<x-field name="phone" label="Mobile number" type="tel" :value="$user->phone" placeholder="01XXXXXXXXX" />
<x-field name="team_id" label="Primary team" :value="$user->team_id" :options="$teams->pluck('name','id')" required />
<x-field class="full-width" name="team_ids" label="Team memberships" hint="The primary team is included. Use Ctrl or Command to select additional teams." :value="$user->exists ? $user->teamIds() : []" :options="$teams->pluck('name','id')" multiple />
<x-field name="password" :label="$user->exists?'New password (leave blank to keep)':'Password (at least 12 characters)'" type="password" :required="!$user->exists" autocomplete="new-password" />
<x-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
<label class="checkbox-field"><input name="active" type="checkbox" value="1" @checked(old('active',$user->active))> Active account</label>
@if(auth()->user()->isSuperAdmin())<input type="hidden" name="is_superadmin" value="0"><label class="checkbox-field"><input name="is_superadmin" type="checkbox" value="1" @checked(old('is_superadmin',$user->is_superadmin))> Superadmin (all workspaces and management controls)</label>@endif
<label class="checkbox-field"><input name="payroll_authorized" type="checkbox" value="1" @checked(old('payroll_authorized',$user->payroll_authorized))> Authorize payroll access (requires Operations membership)</label>
<div class="form-actions"><button class="btn">Save employee</button></div></form>@endsection
