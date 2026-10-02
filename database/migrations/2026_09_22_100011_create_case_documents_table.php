<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files belonging to a case. Only the storage path is kept in MySQL;
     * the actual filesystem/storage layer is fleshed out in a later phase.
     */
    public function up(): void
    {
        Schema::create('case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('category')->nullable();

            $table->timestamps();

            $table->index(['case_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_documents');
    }
};
