<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-reviewed identity verification for a lawyer account.
     * Only file paths are stored -- never binary image data -- and no
     * biometric/face-recognition processing happens here, only upload
     * + manual administrative review.
     */
    public function up(): void
    {
        Schema::create('lawyer_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('cnic_image_path')->nullable();
            $table->string('face_image_path')->nullable();

            $table->string('status', 20)->default('pending')->index();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lawyer_verifications');
    }
};
