<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 10 reads the framework's own `notifications` table by
     * (notifiable, read state) and newest-first. The stock morphs index
     * covers the owner lookup only; this composite serves the unread
     * count and the unread/read filters, and lets the newest-first
     * ordering avoid a filesort. Additive index only -- no column or
     * data change.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'notifications_center_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_center_index');
        });
    }
};
