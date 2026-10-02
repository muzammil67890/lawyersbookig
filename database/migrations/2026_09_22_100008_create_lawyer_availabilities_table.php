<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foundation for the future appointment-booking engine (Phase 6+).
     * Supports either a weekly recurring slot (`day_of_week` set,
     * `specific_date` null) or a one-off date override
     * (`specific_date` set, `day_of_week` null) without needing a
     * schema change later.
     */
    public function up(): void
    {
        Schema::create('lawyer_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lawyer_profile_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week')->nullable()->comment('0=Sunday .. 6=Saturday, for recurring slots');
            $table->date('specific_date')->nullable()->comment('overrides day_of_week for a one-off slot');

            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('is_recurring')->default(true);
            $table->boolean('is_available')->default(true);

            $table->timestamps();

            $table->index(['lawyer_profile_id', 'day_of_week']);
            $table->index(['lawyer_profile_id', 'specific_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyer_availabilities');
    }
};
