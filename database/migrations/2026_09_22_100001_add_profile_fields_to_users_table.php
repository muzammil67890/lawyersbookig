<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2: extend the Phase 1 `users` table with the account-level
     * fields every role needs (phone, avatar, status). Role-specific
     * data (bio, fee, location, etc.) intentionally lives in
     * `lawyer_profiles`, not here — see LawyerProfile.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('profile_image')->nullable()->after('phone');
            $table->string('status', 20)->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'profile_image', 'status']);
        });
    }
};
