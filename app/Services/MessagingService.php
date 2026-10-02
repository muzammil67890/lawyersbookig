<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Centralizes all Phase 8 private-messaging business logic:
 * conversation lookup/creation/reuse, lawyer eligibility, authorized
 * message sending, and read-state operations. Controllers stay thin
 * and call this service instead of touching Conversation/Message
 * directly, mirroring LegalCaseStatusService and
 * AppointmentAvailabilityService from earlier phases.
 *
 * Identity is never taken from request input here: every method takes
 * an already-authenticated User instance the caller obtained from
 * auth()->user() / $request->user() -- never an id read out of the
 * request body. Sender spoofing and participant spoofing (requirement
 * #11) are structurally impossible through this service because there
 * is no parameter through which a caller-supplied id could reach a
 * `sender_id`/`customer_id`/`lawyer_profile_id` column.
 */
class MessagingService
{
    /**
     * Starts a new conversation between $customer and the lawyer
     * behind $lawyerProfileId, or returns the existing one if the pair
     * has already spoken (requirement #10: no duplicate conversations).
     *
     * Eligibility is re-resolved from LawyerProfile::searchable() --
     * the single canonical "approved + active + public + owning
     * account active" definition already used by the Phase 5 directory
     * and matching engine (requirement #8) -- so a pending, rejected,
     * inactive, or suspended lawyer can never be the target of a new
     * (or reused) conversation, however the lawyer id was supplied.
     */
    public function startOrResumeConversation(User $customer, int $lawyerProfileId): Conversation
    {
        abort_unless($customer->isCustomer(), 403, 'Only customers can start a conversation with a lawyer.');

        $lawyerProfile = LawyerProfile::query()->searchable()->find($lawyerProfileId);

        if (! $lawyerProfile) {
            abort(404, 'This lawyer is not currently available to message.');
        }

        return DB::transaction(function () use ($customer, $lawyerProfile) {
            $existing = Conversation::query()
                ->where('customer_id', $customer->id)
                ->where('lawyer_profile_id', $lawyerProfile->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            try {
                return Conversation::create([
                    'customer_id' => $customer->id,
                    'lawyer_profile_id' => $lawyerProfile->id,
                    'status' => ConversationStatus::ACTIVE->value,
                ]);
            } catch (QueryException $exception) {
                // The unique (customer_id, lawyer_profile_id) constraint was
                // hit by a concurrent request that won the race between our
                // SELECT and our INSERT -- reuse its row instead of
                // surfacing a 500 to the customer.
                $existing = Conversation::query()
                    ->where('customer_id', $customer->id)
                    ->where('lawyer_profile_id', $lawyerProfile->id)
                    ->first();

                if (! $existing) {
                    throw $exception;
                }

                return $existing;
            }
        });
    }

    /**
     * Conversations belonging to $user, most recent activity first,
     * with each conversation's unread-for-$user count preloaded so the
     * conversation list never runs an extra query per row (requirement
     * #27/#28). Never returns another user's conversations, and never
     * returns anything for a role other than customer/lawyer
     * (requirement #15: no admin conversation list).
     */
    public function conversationsFor(User $user): Builder
    {
        $query = Conversation::query()->with([
            'customer:id,name,profile_image',
            'lawyerProfile:id,display_name,title,profile_image',
            'latestMessage:id,conversation_id,sender_id,body,created_at',
        ]);

        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        } elseif ($user->isLawyer() && $user->lawyerProfile !== null) {
            $query->where('lawyer_profile_id', $user->lawyerProfile->id);
        } else {
            $query->whereNull('id');
        }

        return $query
            ->withCount(['messages as unread_count' => fn (Builder $q) => $q->unread()->where('sender_id', '!=', $user->id)])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC');
    }

    /** Messages in $conversation, most recent first (paginate() in the controller renders oldest-first per page). */
    public function messagesFor(Conversation $conversation): Builder
    {
        return $conversation->messages()->with('sender:id,name,profile_image,role')->latest('created_at');
    }

    /**
     * Creates a message from $sender in $conversation (requirement
     * #12). $sender is always the authenticated user the controller
     * passed in -- never a value derived from request input -- so
     * message authorship can never be spoofed (requirement #11).
     *
     * The conversation must already have been authorized
     * (ConversationPolicy::sendMessage) by the caller; this method
     * re-checks membership defensively via Conversation::isParticipant()
     * so it is never safe to call with an arbitrary conversation/user
     * pair even if a future caller forgets the policy check.
     */
    public function sendMessage(User $sender, Conversation $conversation, string $body): Message
    {
        abort_unless($conversation->isParticipant($sender), 403);

        $body = trim($body);
        abort_if($body === '', 422, 'Message cannot be empty.');

        $message = DB::transaction(function () use ($sender, $conversation, $body) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });

        $this->notifyOtherParty($sender, $conversation);

        return $message;
    }

    /**
     * Marks every message in $conversation NOT sent by $user as read
     * (requirement #22). A no-op -- never an error -- for a user who
     * is not a party to the conversation, so a defensive call never
     * lets one user mark another user's private messages as read.
     */
    public function markConversationRead(User $user, Conversation $conversation): void
    {
        if (! $conversation->isParticipant($user)) {
            return;
        }

        $conversation->messages()
            ->unread()
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);
    }

    /** Total unread messages across every conversation $user participates in -- for dashboard/nav badges. */
    public function totalUnreadCount(User $user): int
    {
        $query = Message::query()->unread()->where('sender_id', '!=', $user->id);

        if ($user->isCustomer()) {
            $query->whereHas('conversation', fn (Builder $q) => $q->where('customer_id', $user->id));
        } elseif ($user->isLawyer() && $user->lawyerProfile !== null) {
            $query->whereHas('conversation', fn (Builder $q) => $q->where('lawyer_profile_id', $user->lawyerProfile->id));
        } else {
            return 0;
        }

        return $query->count();
    }

    private function notifyOtherParty(User $sender, Conversation $conversation): void
    {
        $recipient = $conversation->otherPartyFor($sender);

        if ($recipient !== null && $recipient->id !== $sender->id) {
            $recipient->notify(new NewMessageNotification($conversation, $sender));
        }
    }
}
