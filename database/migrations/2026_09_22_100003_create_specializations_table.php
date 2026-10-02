<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Finer-grained specializations nested under a broad legal service
     * (e.g. "Divorce" under Family Law). Kept as its own normalized
     * table -- rather than a JSON column -- so the future AI matching
     * system can filter/search on it directly.
     */
    public function up(): void
    {
        Schema::create('specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['service_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specializations');
    }
};
