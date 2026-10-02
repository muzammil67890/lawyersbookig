<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4: a short public-facing professional title/headline
     * (e.g. "Family & Divorce Lawyer"), distinct from `display_name`
     * (the lawyer's name) and `bio`/`experience_summary` (long-form
     * text). This is the one field the Phase 4 lawyer-onboarding
     * workflow needs that Phase 2's schema does not already provide;
     * every other onboarding field reuses an existing column.
     */
    public function up(): void
    {
        Schema::table('lawyer_profiles', function (Blueprint $table) {
            $table->string('title')->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('lawyer_profiles', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
