@extends('layouts.minimal')

@section('title', 'Messages | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Messages</h1>
            <p>Private conversations customers have started with you.</p>
        </div>
        <a class="btn outline" href="{{ route('lawyer.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    <div class="panel" style="padding:0;">
        @if ($conversations->isEmpty())
            <div class="empty" style="border:0;">
                <i class="fa-regular fa-comments"></i>
                <h3>No conversations yet</h3>
                <p>When a customer messages you, it will appear here.</p>
            </div>
        @else
            <div class="table-wrap" style="border:0;border-radius:0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Last message</th>
                            <th>When</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($conversations as $conversation)
                            <tr>
                                <td><strong>{{ $conversation->customer->name ?? 'Customer' }}</strong></td>
                                <td style="max-width:320px;white-space:normal;">
                                    @if ($conversation->latestMessage)
                                        {{ \Illuminate\Support\Str::limit($conversation->latestMessage->body, 70) }}
                                    @else
                                        <span class="subtext" style="margin:0;">No messages yet</span>
                                    @endif
                                </td>
                                <td>{{ optional($conversation->last_message_at ?? $conversation->created_at)->diffForHumans() }}</td>
                                <td>
                                    @if ($conversation->unread_count > 0)
                                        <span class="unread" style="display:inline-grid;">{{ $conversation->unread_count }}</span>
                                    @endif
                                </td>
                                <td><a class="btn outline small" href="{{ route('lawyer.messages.show', $conversation) }}">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:18px 22px;">
                {{ $conversations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
