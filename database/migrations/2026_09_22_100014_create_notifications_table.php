<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's standard database-notifications schema (matches the
     * output of `notifications:table`). Using the framework-native
     * table means `User::notify()`, `$user->notifications` and
     * `$user->unreadNotifications` (from the `Notifiable` trait already
     * on `User`) work immediately once Phase 3+ adds real Notification
     * classes for appointment updates, lawyer approval, new messages,
     * case updates, document events, and system notifications.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
