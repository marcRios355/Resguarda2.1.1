<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las fotografías se guardan en fragmentos codificados en base64 para que
     * cada fila quede por debajo del tamaño máximo de paquete de MySQL.
     */
    public function up(): void
    {
        Schema::create('stored_image_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stored_image_id')->constrained('stored_images')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->longText('data');

            $table->unique(['stored_image_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stored_image_chunks');
    }
};
