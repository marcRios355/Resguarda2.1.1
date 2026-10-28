<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safe_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('address', 255);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('capacity')->default(100);
            $table->integer('current_occupancy')->default(0);
            $table->enum('status', [
                'available',     // 🟢 Disponible
                'limited',       // 🟡 Capacidad limitada
                'almost_full',   // 🟠 Casi llena
                'saturated',     // 🔴 Saturada
                'unavailable',    // ⚫ No disponible
            ])->default('available');
            $table->json('services')->nullable(); // Ej: ['agua', 'luz', 'primeros_auxilios', 'banos']
            $table->string('image_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safe_zones');
    }
};
