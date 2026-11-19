<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    @include('partials.theme-init')
    <title>@yield('title', 'Sign in') · Orbit Office</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <a class="skip-link" href="#auth-content">Skip to form</a>
    <div class="auth-shell">
        <header class="auth-header">
            <a class="auth-brand" href="{{ route('login') }}" aria-label="Orbit Office home">
                <span class="auth-brand-mark" aria-hidden="true">o<span></span></span>
                <span><strong>orbit</strong><small>Office workspace</small></span>
            </a>
            <label class="auth-theme"><span>Appearance</span><select data-theme-select aria-label="Appearance"><option value="light">Light</option><option value="dark">Dark</option><option value="system" selected>System</option></select></label>
        </header>
        <main class="auth-main">
            <aside class="auth-intro" aria-label="About Orbit Office">
                <div class="auth-intro-content">
                    <p class="eyebrow">A shared place for your work</p>
                    <h2>Good work starts<br>with a clear view.</h2>
                    <p class="auth-intro-description">Bring your projects, people and daily operations together. Keep the next step in sight.</p>
                    <div class="auth-workflow" aria-label="Connected teams">
                        <span>Operations</span><span>Creative</span><span>Development</span><span>Marketing</span>
                    </div>
                </div>
                <div class="auth-intro-footer"><span class="auth-orbit-symbol" aria-hidden="true"></span><p>One workspace.<br>Room for every team.</p></div>
            </aside>
            <section class="auth-form-panel" id="auth-content" aria-labelledby="auth-heading">
                <div class="auth-form-content">
                    <p class="eyebrow">@yield('auth-eyebrow')</p>
                    <h1 id="auth-heading">@yield('auth-heading')</h1>
                    <p class="auth-description">@yield('auth-description')</p>
                    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
                    @if($errors->any())<div class="alert alert-error" role="alert"><strong>Please check your details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    @yield('auth-form')
                </div>
            </section>
        </main>
        <footer class="auth-footer"><span>Orbit Office</span><span>Private company workspace</span></footer>
    </div>
</body>
</html>
