<?php

namespace App\Support;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Phase 10: turns a stored database notification into a small, view-safe
 * array. It is a whitelist, not a passthrough: only the category, a short
 * plain-text message, timestamps, read state and a destination URL leave
 * this class. The raw payload (ids, reasons, anything else) never reaches
 * a view, so a future notification class cannot leak data by accident.
 *
 * Destination URLs are built only from integer ids in the payload and
 * only to routes that already exist for the recipient's own role. They
 * are plain links: the destination page still runs its own policy, so a
 * link can never grant access -- at worst it 403/404s.
 */
class NotificationPresenter
{
    private const MESSAGE_LIMIT = 200;

    /**
     * @return array{id:string,category:NotificationCategory,message:string,url:?string,is_read:bool,created_at:\Illuminate\Support\Carbon}
     */
    public function present(DatabaseNotification $notification, User $user): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : null;

        return [
            'id' => $notification->id,
            'category' => NotificationCategory::fromType($type),
            'message' => $this->message($data),
            'url' => $this->destination($type, $data, $user),
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at,
        ];
    }

    private function message(array $data): string
    {
        $message = $data['message'] ?? null;

        if (! is_string($message) || trim($message) === '') {
            return 'You have a new notification.';
        }

        // Plain text only; Blade escapes on output as well.
        return Str::limit(strip_tags($message), self::MESSAGE_LIMIT);
    }

    private function id(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        return is_int($value) && $value > 0 ? $value : null;
    }

    private function destination(?string $type, array $data, User $user): ?string
    {
        $prefix = match ($user->role) {
            UserRole::CUSTOMER => 'customer',
            UserRole::LAWYER => 'lawyer',
            UserRole::ADMIN => 'admin',
            default => null,
        };

        if ($prefix === null || $type === null) {
            return null;
        }

        $category = NotificationCategory::fromType($type);

        switch ($category) {
            case NotificationCategory::APPOINTMENT:
                $id = $this->id($data, 'appointment_id');

                return $id && $prefix !== 'admin' ? route($prefix.'.appointments.show', $id) : null;

            case NotificationCategory::MESSAGE:
                $id = $this->id($data, 'conversation_id');

                return $id && $prefix !== 'admin' ? route($prefix.'.messages.show', $id) : null;

            case NotificationCategory::CASE:
            case NotificationCategory::DOCUMENT:
                // Documents are listed (and downloaded) inside the case page,
                // so a document notification links to the case, never to a
                // file or storage path.
                $id = $this->id($data, 'case_id');

                return $id ? route($prefix.'.cases.show', $id) : null;

            case NotificationCategory::REVIEW:
                return $prefix === 'lawyer' ? route('lawyer.reviews.index') : null;

            default:
                return match ($type) {
                    'lawyer_application_submitted' => $prefix === 'admin' ? route('admin.lawyer-verifications.index') : null,
                    'lawyer_application_approved', 'lawyer_application_rejected' => $prefix === 'lawyer' ? route('lawyer.dashboard') : null,
                    default => null,
                };
        }
    }
}
