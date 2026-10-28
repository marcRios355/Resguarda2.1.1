<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['lost', 'found']);
            $table->enum('category', [
                'electronics',
                'documents',
                'keys',
                'clothing',
                'accessories',
                'pets',
                'other',
            ]);
            $table->string('title', 150);
            $table->text('description');
            $table->date('event_date');
            $table->string('approximate_zone', 150);
            $table->string('photo_path', 255)->nullable();
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->timestamps();

            $table->index(['type', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_items');
    }
};
