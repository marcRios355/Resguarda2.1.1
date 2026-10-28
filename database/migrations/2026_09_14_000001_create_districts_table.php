<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->enum('risk_level', [
                'safe',        // 🟢 Seguro
                'caution',     // 🟡 Precaución
                'risk',        // 🟠 En riesgo
                'high_risk',   // 🔴 Riesgo alto
                'critical',    // 🟣 Riesgo total / crítico
                'clearing',     // ⚪ En proceso de despeje
            ])->default('safe');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->json('geojson_bounds')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
