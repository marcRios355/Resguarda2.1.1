<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sos_alert_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_alert_id')->constrained()->cascadeOnDelete();
            $table->char('recipient_hash', 64);
            $table->text('recipient_email');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('first_attempted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('failure_code', 50)->nullable();
            $table->timestamps();

            $table->unique(['sos_alert_id', 'recipient_hash']);
            $table->index(['sos_alert_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sos_alert_deliveries');
    }
};
