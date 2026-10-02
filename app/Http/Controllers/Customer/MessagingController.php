<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 8: customer side of private customer <-> lawyer messaging.
 *
 * Every conversation this controller touches is either scoped to
 * `$request->user()` by MessagingService::conversationsFor(), or
 * additionally gated by ConversationPolicy from the `{conversation}`
 * route binding -- the same two-layer pattern used by every other
 * customer controller in this app (appointments, cases). Business
 * logic (eligibility, duplicate prevention, read state, notifications)
 * lives entirely in MessagingService; this controller only validates,
 * authorizes, and renders.
 */
class MessagingController extends Controller
{
    public function __construct(private readonly MessagingService $messaging)
    {
    }

    /** The authenticated customer's own conversations -- never another customer's. */
    public function index(Request $request): View
    {
        $conversations = $this->messaging->conversationsFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('customer.messages.index', ['conversations' => $conversations]);
    }

    /**
     * Starts (or resumes) a conversation with an eligible lawyer, then
     * takes the customer straight to it. {lawyer} is re-resolved
     * through LawyerProfile::searchable() inside the service -- never
     * trusted as-is -- exactly like the appointment booking and case
     * creation flows.
     */
    public function start(Request $request, int $lawyer): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $conversation = $this->messaging->startOrResumeConversation($request->user(), $lawyer);

        return redirect()->route('customer.messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $messages = $this->messaging->messagesFor($conversation)->paginate(30)->withQueryString();
        $this->messaging->markConversationRead($request->user(), $conversation);

        $conversation->load('lawyerProfile:id,display_name,title,profile_image');

        // A light sidebar of the customer's other conversations, so the
        // chat page doubles as a quick switcher -- the same CSS the
        // Phase 1 chat demo already ships (`.conversation-list`).
        $sidebar = $this->messaging->conversationsFor($request->user())->limit(20)->get();

        return view('customer.messages.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'sidebar' => $sidebar,
        ]);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('sendMessage', $conversation);

        $this->messaging->sendMessage($request->user(), $conversation, $request->validated('body'));

        return redirect()->route('customer.messages.show', $conversation);
    }
}
