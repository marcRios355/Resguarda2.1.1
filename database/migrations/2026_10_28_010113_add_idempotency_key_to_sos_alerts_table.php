<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sos_alerts', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable();
            $table->unique(['user_id', 'idempotency_key']);
        });

        DB::table('sos_alerts')
            ->whereNull('idempotency_key')
            ->where('delivery_status', 'pending')
            ->update(['delivery_status' => 'registered']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('sos_alerts')->whereNotNull('idempotency_key')->exists()) {
            throw new RuntimeException(
                'Cannot roll back SOS idempotency while alerts still contain idempotency keys.',
            );
        }

        Schema::table('sos_alerts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
