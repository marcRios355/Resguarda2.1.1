<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alert_emergency_contact', function (Blueprint $table) {
            $table->foreignId('sos_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('emergency_contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['sos_alert_id', 'emergency_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alert_emergency_contact');
    }
};
