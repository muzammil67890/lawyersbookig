<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Legal matter/case foundation. Case-management UI is a later phase. */
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();

            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lawyer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending')->index();

            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['lawyer_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
