<?php

namespace App\Http\Controllers\Lawyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 8: lawyer side of private customer <-> lawyer messaging.
 *
 * A lawyer never initiates a conversation (only a customer may -- see
 * ConversationPolicy::create()); a lawyer can only view and reply to
 * conversations customers have already started with them. Every
 * conversation this controller touches is either scoped to
 * `$request->user()` by MessagingService::conversationsFor(), or
 * additionally gated by ConversationPolicy from the `{conversation}`
 * route binding -- the same two-layer pattern used by
 * Lawyer\AppointmentController and Lawyer\CaseController.
 */
class MessagingController extends Controller
{
    public function __construct(private readonly MessagingService $messaging)
    {
    }

    /** The authenticated lawyer's own conversations -- never another lawyer's. */
    public function index(Request $request): View
    {
        $conversations = $this->messaging->conversationsFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('lawyer.messages.index', ['conversations' => $conversations]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $messages = $this->messaging->messagesFor($conversation)->paginate(30)->withQueryString();
        $this->messaging->markConversationRead($request->user(), $conversation);

        $conversation->load('customer:id,name,profile_image');

        $sidebar = $this->messaging->conversationsFor($request->user())->limit(20)->get();

        return view('lawyer.messages.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'sidebar' => $sidebar,
        ]);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('sendMessage', $conversation);

        $this->messaging->sendMessage($request->user(), $conversation, $request->validated('body'));

        return redirect()->route('lawyer.messages.show', $conversation);
    }
}
