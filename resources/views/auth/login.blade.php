@extends('layouts.auth')
@section('title', 'Sign in')
@section('auth-eyebrow', 'Your company workspace')
@section('auth-heading', 'Welcome back.')
@section('auth-description', 'Sign in to pick up where you left off.')
@section('auth-form')
<form action="{{ route('login.store') }}" method="post" class="auth-form">
    @csrf
    <x-field name="email" label="Work email" type="email" required autofocus autocomplete="username" placeholder="you@company.com" />
    <x-field name="password" label="Password" type="password" required autocomplete="current-password" />
    <div class="auth-form-options">
        <label class="checkbox-field"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in</label>
        @if(Route::has('password.request'))<a class="text-link" href="{{ route('password.request') }}">Forgot password?</a>@endif
    </div>
    <button class="btn auth-submit" type="submit">Sign in to workspace</button>
</form>
<p class="auth-help">Need access? Contact your workspace administrator.</p>
@endsection
