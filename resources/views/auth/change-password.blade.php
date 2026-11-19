@extends('layouts.app')
@section('title', 'Change password')
@section('content')
<div class="page-header">
    <div><p class="eyebrow">Your account</p><h1>Change password</h1><p>Manage the password you use to access your workspace.</p></div>
    <a class="btn-secondary" href="{{ route('dashboard') }}">Back to dashboard</a>
</div>
<section class="card form-card password-settings" aria-labelledby="password-settings-heading">
    <div class="card-heading"><div><h2 id="password-settings-heading">Password details</h2><p class="muted">Choose a different new password with at least 12 characters.</p></div></div>
    <form action="{{ route('password.update') }}" method="post" class="grid-form">
        @csrf
        @method('put')
        <x-field class="full-width" name="current_password" label="Current password" type="password" required autofocus autocomplete="current-password" />
        <x-field name="password" label="New password" type="password" required autocomplete="new-password" />
        <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <div class="form-actions full-width">
            <button class="btn" type="submit">Save password</button>
            <a class="btn-secondary" href="{{ route('dashboard') }}">Cancel</a>
        </div>
    </form>
</section>
@endsection
