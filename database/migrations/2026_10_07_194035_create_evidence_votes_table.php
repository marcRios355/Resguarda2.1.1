<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('citizen_evidences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('vote_type', ['confirm', 'mismatch', 'outdated']);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['evidence_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_votes');
    }
};
