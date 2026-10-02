<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Legal Connect — legal services and lawyer discovery platform.">
    <title>@yield('title', 'Legal Connect')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/legal-connect.css') }}">
    @stack('head')
</head>
<body>
    {{-- Phase 10: notification entry for authenticated pages. layouts.site
         (public pages) renders its own bell in its header, so skip here. --}}
    @auth
        @sectionMissing('site-content')
            <div class="notif-strip">
                <div class="container">
                    <a class="btn ghost small" href="{{ route('notifications.index') }}" aria-label="Notifications{{ ($unreadNotificationsCount ?? 0) > 0 ? ', '.$unreadNotificationsCount.' unread' : '' }}">
                        <i class="fa-regular fa-bell"></i> Notifications
                        @if (($unreadNotificationsCount ?? 0) > 0)
                            <span class="unread" style="display:inline-grid;vertical-align:middle;margin-left:4px;">{{ $unreadNotificationsCount }}</span>
                        @endif
                    </a>
                </div>
            </div>
        @endif
    @endauth
    @yield('content')
    @stack('scripts')
</body>
</html>
