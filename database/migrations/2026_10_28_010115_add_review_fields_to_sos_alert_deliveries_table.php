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
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_outcome', 30)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('sos_alert_deliveries')
            ->whereNotNull('reviewed_at')
            ->orWhereNotNull('reviewed_by')
            ->orWhereNotNull('review_outcome')
            ->exists()) {
            throw new RuntimeException(
                'Cannot roll back SOS delivery review history while reviewed deliveries exist.',
            );
        }

        Schema::table('sos_alert_deliveries', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['reviewed_at', 'reviewed_by', 'review_outcome']);
        });
    }
};
