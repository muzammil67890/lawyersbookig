<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-to-one extension of a `role = lawyer` user with all the
     * professional/public-profile data that does not belong on `users`.
     *
     * `verification_status` and `is_active` are intentionally duplicated
     * (denormalized) here from `lawyer_verifications` / admin actions so
     * the public lawyer-search query (filter by service, specialization,
     * city, fee, approval+active status) never has to join the
     * verification-review table just to render results.
     */
    public function up(): void
    {
        Schema::create('lawyer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('phone', 30)->nullable();

            $table->string('city')->nullable()->index();
            $table->string('location')->nullable();
            $table->string('address')->nullable();

            $table->unsignedSmallInteger('years_of_experience')->default(0);
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->decimal('starting_fee', 10, 2)->nullable();

            $table->text('experience_summary')->nullable();
            $table->text('education')->nullable();
            $table->string('bar_council')->nullable();
            $table->string('license_number')->nullable();

            $table->string('profile_image')->nullable();

            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->string('verification_status', 20)->default('pending')->index();

            $table->timestamps();

            $table->index(['verification_status', 'is_active', 'city']);
            $table->index(['consultation_fee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyer_profiles');
    }
};
