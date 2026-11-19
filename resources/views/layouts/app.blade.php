<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    @include('partials.theme-init')
    <title>@yield('title', 'Workspace') · Orbit Office</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
@auth
@php
    $viewer = auth()->user();
    $dashboardTeams = $viewer->isSuperAdmin() ? App\Models\Team::orderBy('name')->get() : $viewer->teams()->orderBy('name')->get();
    $selectedDashboard = request()->routeIs('dashboard.team') ? request()->route('team') : session('active_dashboard_team');
    $activeDashboard = $dashboardTeams->firstWhere('slug', $selectedDashboard) ?? $viewer->team ?? $dashboardTeams->first();
    $unreadCount = $viewer->unreadNotifications()->count();
    $navGroups = [
        'Client referrals' => [['referral.create','Bring a Client','user-plus',true],['referral.index','My referrals','handshake',true],['marketing_update.index','Marketing updates','list-check',$viewer->inTeam('admin','marketing')]],
        'Workspace' => [['dashboard', 'Overview', 'table-columns', true], ['tasks.index', 'Tasks', 'list-check', true], ['projects.index', 'Projects', 'folder-open', true], ['clients.index', 'Clients', 'address-book', true], ['development.index', 'Development', 'code', $viewer->inTeam('admin', 'operations', 'developer')]],
        'Growth & creative' => [['leads.index', 'Leads', 'arrow-trend-up', $viewer->inTeam('admin', 'marketing')], ['campaigns.index', 'Campaigns', 'bullhorn', $viewer->inTeam('admin', 'marketing')], ['briefs.index', 'Creative briefs', 'pen-ruler', $viewer->inTeam('admin', 'marketing', 'graphics')], ['queries.index', 'Client queries', 'comment-dots', $viewer->inTeam('admin', 'operations', 'marketing', 'developer')]],
        'Company' => [['employees.index', 'Employees', 'id-badge', $viewer->canPayroll()], ['payroll.index', 'Payroll', 'wallet', $viewer->canPayroll()], ['releases.index', 'Software updates', 'circle-up', true], ['users.index', 'People & teams', 'users', $viewer->isAdmin()], ['audit.index', 'Activity history', 'clock-rotate-left', $viewer->isAdmin()]],
    ];
@endphp
<div class="app-shell">
    <button class="sidebar-backdrop" data-sidebar-close type="button" aria-label="Close navigation" tabindex="-1" hidden></button>
    <aside class="sidebar" id="sidebar" aria-label="Workspace sidebar">
        <div class="sidebar-brand-row">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="Orbit Office home"><span class="brand-mark" aria-hidden="true">o<span></span></span><span>orbit<span class="brand-sub">OFFICE</span></span></a>
            <button class="icon-button sidebar-close" data-sidebar-close type="button" aria-label="Close navigation"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="workspace-switch"><span class="workspace-symbol" aria-hidden="true">O</span><div><strong>Company workspace</strong><small>{{ $activeDashboard?->name }}</small></div></div>
        @if($dashboardTeams->count() > 1)
            <details class="dashboard-switcher" data-popover>
                <summary><span>Dashboard</span><strong>{{ $activeDashboard?->name }}</strong><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                <nav aria-label="Switch dashboard">
                    @foreach($dashboardTeams as $dashboardTeam)
                        <a href="{{ route('dashboard.team', $dashboardTeam->slug) }}" @if($activeDashboard?->id === $dashboardTeam->id) aria-current="page" @endif><span>{{ $dashboardTeam->name }}</span>@if($viewer->team_id === $dashboardTeam->id)<small>Primary</small>@endif</a>
                    @endforeach
                </nav>
            </details>
        @endif
        <nav class="main-nav" aria-label="Main navigation">
            @foreach($navGroups as $group => $items)
                @if(collect($items)->contains(fn($item) => $item[3] && Route::has($item[0])))
                    <div class="nav-group"><div class="nav-heading">{{ $group }}</div>
                    @foreach($items as [$routeName, $label, $icon, $visible])
                        @if($visible && Route::has($routeName))
                            @php $active = request()->routeIs(explode('.', $routeName)[0].'.*') || request()->routeIs($routeName) || ($routeName === 'users.index' && request()->routeIs('teams.*')) || ($routeName === 'campaigns.index' && request()->routeIs('posts.*')); @endphp
                            <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ $routeName === 'dashboard' ? ($activeDashboard ? route('dashboard.team', $activeDashboard->slug) : route('dashboard')) : route($routeName) }}" @if($active) aria-current="page" @endif><i class="nav-icon fa-solid fa-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
                        @endif
                    @endforeach
                    </div>
                @endif
            @endforeach
        </nav>
        <div class="sidebar-bottom"><a class="nav-link {{ request()->routeIs('password.change') ? 'active' : '' }}" href="{{ route('password.change') }}" @if(request()->routeIs('password.change')) aria-current="page" @endif><i class="nav-icon fa-solid fa-gear" aria-hidden="true"></i><span>Change password</span></a><span class="workspace-caption">Orbit Office workspace</span></div>
    </aside>
    <div class="main-column">
        <header class="topbar">
            <div class="topbar-location"><button type="button" class="icon-button mobile-menu" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false" data-sidebar-toggle><i class="fa-solid fa-bars" aria-hidden="true"></i></button><a class="topbar-workspace" href="{{ route('dashboard') }}">Workspace</a><span class="breadcrumb-divider" aria-hidden="true">/</span><strong>@yield('title', 'Overview')</strong></div>
            <div class="topbar-actions">
                <label class="theme-control"><span class="sr-only">Color theme</span><select data-theme-select aria-label="Color theme"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></label>
                <a class="notification-link icon-button" href="{{ route('notifications.index') }}" aria-label="Notifications ({{ $unreadCount }} unread)"><i class="fa-regular fa-bell" aria-hidden="true"></i>@if($unreadCount)<span class="notification-dot"></span>@endif</a>
                <details class="account-menu" data-popover>
                    <summary aria-label="Account menu for {{ $viewer->name }}"><span class="profile-avatar">{{ mb_strtoupper(mb_substr($viewer->name, 0, 1)) }}</span><span class="profile-label"><strong>{{ $viewer->name }}</strong><small>{{ $viewer->team?->name }}</small></span><i class="fa-solid fa-chevron-down account-chevron" aria-hidden="true"></i></summary>
                    <div class="account-panel"><div class="account-identity"><strong>{{ $viewer->name }}</strong><small>{{ $viewer->email }}</small></div><a href="{{ route('password.change') }}">Change password</a><a href="{{ route('notifications.index') }}">Notifications @if($unreadCount)<span>{{ $unreadCount }}</span>@endif</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></div>
                </details>
            </div>
        </header>
        <main class="main-content" id="main-content" tabindex="-1">
            @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
            @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="alert alert-error" role="alert"><strong>Please check the following</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
        <footer class="app-footer"><span>Orbit Office</span><span>Asia/Dhaka <span aria-hidden="true">·</span> BDT</span></footer>
    </div>
</div>
@else
<main class="guest-page" id="main-content">@yield('content')</main>
@endauth
<dialog class="confirm-dialog" data-confirm-dialog aria-labelledby="confirm-title" aria-describedby="confirm-message"><form method="dialog"><h2 id="confirm-title">Confirm action</h2><p id="confirm-message"></p><div class="actions"><button class="btn-secondary" value="cancel">Cancel</button><button class="btn" value="confirm">Continue</button></div></form></dialog>
@stack('scripts')
</body>
</html>
