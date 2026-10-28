<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->foreignId('district_zone_id')->nullable()->constrained('district_zones')->nullOnDelete();
            $table->enum('incident_type', [
                'inundacion',
                'huaico',
                'desborde',
                'calle_bloqueada',
                'materiales_caidos',
                'deslizamiento',
                'sismo',
                'zona_afectada',
                'refugio_saturado',
                'vialidad',
                'otro',
            ]);
            $table->string('title', 150);
            $table->text('description');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address_reference', 255)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->enum('severity', ['baja', 'media', 'alta', 'critica'])->default('media');
            $table->enum('status', ['pending', 'under_review', 'confirmed', 'resolved', 'rejected'])->default('pending');
            $table->boolean('is_still_happening')->default(true);
            $table->integer('confirmation_count')->default(0);
            $table->integer('false_report_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_evidences');
    }
};
