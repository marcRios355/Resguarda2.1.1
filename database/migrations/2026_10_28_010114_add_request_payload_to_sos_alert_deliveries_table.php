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
        Schema::table('sos_alert_deliveries', function (Blueprint $table) {
            $table->text('request_payload')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('sos_alert_deliveries')->whereNotNull('request_payload')->exists()) {
            throw new RuntimeException(
                'Cannot roll back SOS email payload snapshots while deliveries still contain them.',
            );
        }

        Schema::table('sos_alert_deliveries', function (Blueprint $table) {
            $table->dropColumn('request_payload');
        });
    }
};
