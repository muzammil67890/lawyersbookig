@extends('layouts.minimal')

@section('title', 'Notifications | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Notifications
                @if ($unreadCount > 0)
                    <span class="unread" style="display:inline-grid;vertical-align:middle;margin-left:6px;">{{ $unreadCount }}</span>
                @endif
            </h1>
            <p>Appointments, messages, cases, documents, reviews and account updates in one place.</p>
        </div>
        <a class="btn outline" href="{{ route('dashboard.redirect') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    <div class="row-actions" style="margin-bottom:18px;flex-wrap:wrap;gap:6px;align-items:center;">
        @php
            $tabs = ['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'];
        @endphp
        @foreach ($tabs as $value => $label)
            <a class="btn small {{ $filter === $value ? 'gold' : 'outline' }}"
               href="{{ route('notifications.index', $value === 'all' ? [] : ['filter' => $value]) }}">{{ $label }}</a>
        @endforeach

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}" style="margin-left:auto;">
                @csrf
                <button class="btn small outline" type="submit"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
            </form>
        @endif
    </div>

    <div class="panel" style="padding:0;">
        @if ($notifications->isEmpty())
            <div class="empty" style="border:0;">
                <i class="fa-regular fa-bell"></i>
                <h3>
                    @if ($filter === 'unread')
                        You're all caught up
                    @elseif ($filter === 'read')
                        No read notifications
                    @else
                        No notifications yet
                    @endif
                </h3>
                <p>
                    @if ($filter === 'unread')
                        You have no unread notifications.
                    @elseif ($filter === 'read')
                        Notifications you've read will appear here.
                    @else
                        Updates about your appointments, messages and cases will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="table-wrap" style="border:0;border-radius:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Notification</th>
                            <th>When</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notifications as $item)
                            <tr class="{{ $item['is_read'] ? '' : 'notification-unread' }}">
                                <td>
                                    <span class="status"><i class="{{ $item['category']->icon() }}" aria-hidden="true"></i>&nbsp;{{ $item['category']->label() }}</span>
                                </td>
                                <td style="max-width:420px;white-space:normal;">
                                    @if ($item['is_read'])
                                        {{ $item['message'] }}
                                    @else
                                        <strong>{{ $item['message'] }}</strong>
                                        <span class="status pending">New</span>
                                    @endif
                                </td>
                                <td title="{{ $item['created_at']->format('M j, Y g:i A') }}">{{ $item['created_at']->diffForHumans() }}</td>
                                <td>
                                    <div class="row-actions">
                                        @if ($item['url'])
                                            <a class="btn outline small" href="{{ $item['url'] }}">Open</a>
                                        @endif
                                        <form method="POST" action="{{ route($item['is_read'] ? 'notifications.unread' : 'notifications.read', $item['id']) }}">
                                            @csrf
                                            <button class="btn outline small" type="submit">{{ $item['is_read'] ? 'Mark unread' : 'Mark read' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:18px 22px;">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
