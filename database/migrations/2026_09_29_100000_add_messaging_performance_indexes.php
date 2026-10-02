<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 8 adds two real, high-frequency query shapes the Phase 2
     * schema didn't yet need an index for:
     *
     *  - A lawyer's conversation list: `WHERE lawyer_profile_id = ?`.
     *    The existing `unique(customer_id, lawyer_profile_id)` index
     *    is led by `customer_id`, so it can't serve this lookup;
     *    without its own index this degrades to a full table scan as
     *    conversations grow.
     *  - Ordering both parties' conversation lists by most recent
     *    activity (`ORDER BY last_message_at DESC`).
     *  - Per-conversation unread counts / mark-as-read:
     *    `WHERE conversation_id = ? AND read_at IS NULL`. The existing
     *    `(conversation_id, created_at)` index doesn't cover `read_at`,
     *    so this adds the composite index that does.
     *
     * No column changes, no data migration -- additive indexes only.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->index('lawyer_profile_id');
            $table->index('last_message_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['lawyer_profile_id']);
            $table->dropIndex(['last_message_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'read_at']);
        });
    }
};
