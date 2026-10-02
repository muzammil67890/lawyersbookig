@extends('layouts.minimal')

@section('title', ($conversation->lawyerProfile->display_name ?? 'Conversation').' | Messages | Legal Connect')

@section('content')
<div class="chat-view">
    <aside class="conversation-list" aria-label="Your conversations">
        <h2>Messages</h2>
        <a href="{{ route('customer.messages.index') }}" class="btn ghost small" style="margin:0 9px 12px;">
            <i class="fa-solid fa-arrow-left"></i> All conversations
        </a>
        @forelse ($sidebar as $item)
            <a href="{{ route('customer.messages.show', $item) }}"
               class="conversation {{ $item->id === $conversation->id ? 'active' : '' }}"
               aria-current="{{ $item->id === $conversation->id ? 'true' : 'false' }}">
                @if ($item->lawyerProfile && $item->lawyerProfile->profile_image)
                    <img src="{{ asset('storage/'.$item->lawyerProfile->profile_image) }}" alt="">
                @else
                    <div style="width:42px;height:42px;border-radius:11px;background:var(--canvas);display:grid;place-items:center;color:var(--muted);flex:0 0 42px;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                @endif
                <div class="conversation-main">
                    <strong>{{ $item->lawyerProfile->display_name ?? 'Lawyer' }}</strong>
                    <span>{{ $item->latestMessage->body ?? 'No messages yet' }}</span>
                </div>
                @if ($item->unread_count > 0)
                    <span class="unread">{{ $item->unread_count }}</span>
                @endif
            </a>
        @empty
            <p class="subtext" style="padding:0 9px;">No conversations yet.</p>
        @endforelse
    </aside>

    <section class="chat-pane">
        <div class="chat-top">
            <div>
                <strong>{{ $conversation->lawyerProfile->display_name ?? 'Lawyer' }}</strong><br>
                <span class="subtext" style="margin:0;">{{ $conversation->lawyerProfile->title ?? '' }}</span>
            </div>
            <a class="btn outline small" href="{{ route('lawyers.show', $conversation->lawyer_profile_id) }}">
                <i class="fa-regular fa-id-card"></i> View profile
            </a>
        </div>

        <div class="messages">
            @if ($messages->isEmpty())
                <div class="empty" style="border:0;">
                    <i class="fa-regular fa-comment-dots"></i>
                    <h3>Say hello</h3>
                    <p>Send the first message to {{ $conversation->lawyerProfile->display_name ?? 'this lawyer' }}.</p>
                </div>
            @else
                @if ($messages->hasMorePages())
                    <div style="text-align:center;">
                        <a class="btn outline small" href="{{ $messages->nextPageUrl() }}">Load earlier messages</a>
                    </div>
                @endif
                @foreach ($messages->reverse() as $chatMessage)
                    <div class="message {{ $chatMessage->sender_id === auth()->id() ? 'mine' : '' }}">
                        <p style="margin:0;white-space:pre-wrap;word-wrap:break-word;">{{ $chatMessage->body }}</p>
                        <time datetime="{{ $chatMessage->created_at->toIso8601String() }}">
                            {{ $chatMessage->created_at->format('M j, g:i A') }}
                        </time>
                    </div>
                @endforeach
            @endif
        </div>

        <form class="chat-compose" method="POST" action="{{ route('customer.messages.store', $conversation) }}">
            @csrf
            <input id="body" name="body" type="text" maxlength="4000" placeholder="Type a message&hellip;" autocomplete="off" required aria-label="Message">
            <button class="btn gold" type="submit"><i class="fa-solid fa-paper-plane"></i> Send</button>
        </form>
        @error('body')
            <p style="color:var(--red);font-size:12px;padding:0 20px 12px;margin:0;">{{ $message }}</p>
        @enderror
    </section>
</div>
@endsection
