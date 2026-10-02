@extends('layouts.minimal')

{{--
    Phase 5: a lightweight, server-rendered header/footer for real
    (non-hash-routed) pages like the public lawyer directory/profile.
    It reuses the exact same CSS classes as the Phase 1 SPA header/footer
    in resources/views/pages/legal-connect.blade.php so it is visually
    identical, but it does NOT load legal-connect.js: that script's
    navigate()/routeFromHash() assume the SPA's #view-* sections exist in
    the DOM and would throw on a page that doesn't have them. Nav
    visibility (login/register vs dashboard/logout) uses real @auth/@guest
    instead of the demo's window.LC_AUTH JS hack. The notification bell,
    global search popover and AI assistant button are demo-only
    interactive widgets with no real backend yet, so they're intentionally
    left out here rather than faked.
--}}

@section('content')
<div class="app">
<header class="site-header">
<div class="container nav-row">
<a aria-label="Legal Connect home" class="brand" href="{{ route('home') }}"><span class="brand-mark"><i class="fa-solid fa-scale-balanced"></i></span>LEGAL CONNECT</a>
<nav aria-label="Primary navigation" class="primary-nav" id="primaryNav">
<a href="{{ route('home') }}">Home</a>
<a href="{{ route('lawyers.index') }}" class="{{ request()->routeIs('lawyers.*') ? 'active' : '' }}">Find Lawyers</a>
<a href="{{ route('home') }}#services">Services</a>
<a href="{{ route('home') }}#about">About</a>
<a href="{{ route('home') }}#contact">Contact</a>
</nav>
<div class="nav-actions">
@guest
<a class="btn ghost login-nav" href="{{ route('login') }}">Login</a>
<a class="btn small register-nav" href="{{ route('register') }}">Register</a>
@endguest
@auth
<a class="btn ghost small" href="{{ route('notifications.index') }}" aria-label="Notifications{{ ($unreadNotificationsCount ?? 0) > 0 ? ', '.$unreadNotificationsCount.' unread' : '' }}"><i class="fa-regular fa-bell"></i> Notifications @if (($unreadNotificationsCount ?? 0) > 0)<span class="unread" style="display:inline-grid;vertical-align:middle;margin-left:4px;">{{ $unreadNotificationsCount }}</span>@endif</a>
<a class="btn small" href="{{ route('dashboard.redirect') }}"><i class="fa-solid fa-grid-2"></i> Dashboard</a>
<form method="POST" action="{{ route('logout') }}" style="display:inline">
@csrf
<button type="submit" class="btn ghost small" style="border:0;background:transparent;cursor:pointer"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</button>
</form>
@endauth
<button aria-label="Toggle dark mode" class="icon-btn" id="siteThemeToggle" type="button"><i class="fa-regular fa-moon" id="siteThemeIcon"></i></button>
<button aria-label="Open navigation" class="icon-btn mobile-toggle" id="siteMobileToggle" type="button"><i class="fa-solid fa-bars"></i></button>
</div>
</div>
</header>
<main>
@yield('site-content')
</main>
<footer id="footer"><div class="container"><div class="footer-grid"><div class="footer-brand"><a class="brand footer-brand" href="{{ route('home') }}"><span class="brand-mark"><i class="fa-solid fa-scale-balanced"></i></span>LEGAL CONNECT</a><p>Find, compare and connect with legal professionals on Legal Connect.</p></div><div><h4>Platform</h4><a href="{{ route('home') }}#about">About</a><a href="{{ route('home') }}#services">Services</a><a href="{{ route('lawyers.index') }}">Find Lawyers</a><a href="{{ route('register') }}">For Lawyers</a></div><div><h4>Resources</h4><a href="{{ route('home') }}#resources">Knowledge Center</a><a href="{{ route('home') }}#contact">Contact</a></div><div><h4>Get started</h4><a href="{{ route('register') }}">Create account</a><a href="{{ route('login') }}">Sign in</a></div></div><div class="copyright"><span>Copyright 2026 Legal Connect. All rights reserved.</span></div></div></footer>
</div>
@stack('scripts')
<script>
(function () {
    var body = document.body;
    var saved = localStorage.getItem('lc_theme');
    if (saved === 'dark') body.classList.add('dark');
    var icon = document.getElementById('siteThemeIcon');
    function syncIcon() { if (icon) icon.className = body.classList.contains('dark') ? 'fa-solid fa-sun' : 'fa-regular fa-moon'; }
    syncIcon();
    var themeBtn = document.getElementById('siteThemeToggle');
    if (themeBtn) themeBtn.addEventListener('click', function () {
        body.classList.toggle('dark');
        localStorage.setItem('lc_theme', body.classList.contains('dark') ? 'dark' : 'light');
        syncIcon();
    });
    var navToggle = document.getElementById('siteMobileToggle');
    var nav = document.getElementById('primaryNav');
    if (navToggle && nav) navToggle.addEventListener('click', function () { nav.classList.toggle('open'); });
})();
</script>
@endsection
