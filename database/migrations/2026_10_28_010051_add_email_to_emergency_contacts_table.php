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
        Schema::table('emergency_contacts', function (Blueprint $table) {
            $table->text('email')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('emergency_contacts')->whereNotNull('email')->exists()) {
            throw new RuntimeException(
                'Cannot roll back emergency contact emails while addresses are stored.',
            );
        }

        Schema::table('emergency_contacts', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
