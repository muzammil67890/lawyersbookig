<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Private one-to-one customer <-> lawyer conversation thread. No group chat. */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lawyer_profile_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('active')->index();
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->unique(['customer_id', 'lawyer_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
