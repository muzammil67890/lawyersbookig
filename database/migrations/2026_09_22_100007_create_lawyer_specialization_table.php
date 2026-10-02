<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Many-to-many: which fine-grained specializations a lawyer offers. */
    public function up(): void
    {
        Schema::create('lawyer_specialization', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lawyer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lawyer_profile_id', 'specialization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyer_specialization');
    }
};
