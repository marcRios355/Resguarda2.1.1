<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('citizen_evidences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('report_reason', [
                'false_information',
                'old_photo',
                'incorrect_content',
                'incorrect_location',
                'inappropriate_content',
                'spam',
            ]);
            $table->text('notes')->nullable();
            $table->enum('review_status', ['pending', 'valid', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_reports');
    }
};
