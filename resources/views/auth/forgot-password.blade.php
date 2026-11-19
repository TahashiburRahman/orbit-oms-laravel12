@extends('layouts.auth')
@section('title', 'Forgot password')
@section('auth-eyebrow', 'Account recovery')
@section('auth-heading', 'Forgot your password?')
@section('auth-description', 'Enter your work email. We will send you a code to reset your password.')
@section('auth-form')
<form action="{{ route('password.email') }}" method="post" class="auth-form">
    @csrf
    <x-field name="email" label="Work email" type="email" required autofocus autocomplete="username" placeholder="you@company.com" />
    <button class="btn auth-submit" type="submit">Email reset code</button>
</form>
<p class="auth-back"><a class="text-link" href="{{ route('login') }}">Back to sign in</a></p>
@endsection
