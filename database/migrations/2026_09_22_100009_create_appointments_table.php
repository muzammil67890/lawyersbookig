<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Booking foundation. The booking workflow itself is a later phase. */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lawyer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            $table->date('appointment_date');
            $table->time('start_time');
            $table->time('end_time')->nullable();

            $table->string('consultation_type', 20)->default('online');
            $table->string('status', 20)->default('pending')->index();

            $table->text('reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('appointments')->nullOnDelete();

            $table->timestamps();

            $table->index(['lawyer_profile_id', 'appointment_date', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
