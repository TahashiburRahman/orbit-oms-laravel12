@extends('layouts.auth')
@section('title', 'Reset password')
@section('auth-eyebrow', 'Account recovery')
@section('auth-heading', 'Reset your password.')
@section('auth-description')
Enter the email code with {{ $codeLength ?? 6 }} digits and choose a new password. Codes expire after {{ $expiresMinutes ?? 10 }} minutes.
@endsection
@section('auth-form')
<form action="{{ route('password.reset.store') }}" method="post" class="auth-form">
    @csrf
    <label class="field"><span>Work email</span><input type="email" name="email" value="{{ $email }}" readonly required autocomplete="username"></label>
    <label class="field"><span>Email code</span><input class="auth-code" type="text" name="otp" value="" inputmode="numeric" autocomplete="one-time-code" minlength="{{ $codeLength ?? 6 }}" maxlength="{{ $codeLength ?? 6 }}" pattern="[0-9]{{ '{'.($codeLength ?? 6).'}' }}" required autofocus></label>
    <x-field name="password" label="New password (at least 12 characters)" type="password" required autocomplete="new-password" />
    <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
    <button class="btn auth-submit" type="submit">Reset password</button>
</form>
<form action="{{ route('password.email') }}" method="post" class="auth-recovery-actions">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <button class="btn-secondary" type="submit">Resend code</button>
    <a class="text-link" href="{{ route('password.request') }}">Change email</a>
</form>
<p class="auth-back"><a class="text-link" href="{{ route('login') }}">Back to sign in</a></p>
@endsection
