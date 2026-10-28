<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->enum('incident_type', [
                'inundacion',
                'huaico',
                'desborde',
                'calle_bloqueada',
                'zona_en_riesgo',
                'zona_segura',
                'personas_atrapadas',
                'zona_despejada',
                'zona_en_proceso_despeje',
                'otro',
            ]);
            $table->string('title', 150);
            $table->text('description');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('address_reference', 255)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->enum('severity', ['baja', 'media', 'alta', 'critica'])->default('media');
            $table->enum('status', [
                'pending',          // 1 reporte
                'community_alert', // 3 reportes
                'confirmed',       // 5+ reportes
                'rejected',
                'resolved',
            ])->default('pending');
            $table->integer('confirmations_count')->default(0);
            $table->integer('rejections_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
