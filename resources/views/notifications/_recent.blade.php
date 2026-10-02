{{-- Phase 10: compact "Recent notifications" panel for the three dashboards. Expects $recentNotifications and $unreadNotificationsCount. --}}
<div class="panel" style="padding:22px;margin-bottom:18px;">
    <h3>Notifications
        @if (($unreadNotificationsCount ?? 0) > 0)
            <span class="unread" style="display:inline-grid;vertical-align:middle;margin-left:6px;">{{ $unreadNotificationsCount }}</span>
        @endif
    </h3>
    @if ($recentNotifications->isEmpty())
        <p class="subtext">Nothing new yet. Updates about your appointments, messages and cases will show up here.</p>
    @else
        @foreach ($recentNotifications as $item)
            <div class="appointment-row" style="grid-template-columns:1fr auto;">
                <div>
                    @if ($item['is_read'])
                        <span>{{ $item['message'] }}</span>
                    @else
                        <strong>{{ $item['message'] }}</strong>
                    @endif
                    <span>{{ $item['category']->label() }} &middot; {{ $item['created_at']->diffForHumans() }}</span>
                </div>
                @if ($item['url'])
                    <a class="btn outline small" href="{{ $item['url'] }}">Open</a>
                @endif
            </div>
        @endforeach
    @endif
    <a class="btn outline" style="margin-top:12px;" href="{{ route('notifications.index') }}">
        <i class="fa-regular fa-bell"></i> All notifications
    </a>
</div>
