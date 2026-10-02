<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Many-to-many: which broad legal services a lawyer offers. */
    public function up(): void
    {
        Schema::create('lawyer_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lawyer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lawyer_profile_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyer_service');
    }
};
