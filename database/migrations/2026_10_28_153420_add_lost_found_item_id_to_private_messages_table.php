<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('private_messages', function (Blueprint $table) {
            $table->foreignId('lost_found_item_id')
                ->nullable()
                ->after('recipient_id')
                ->constrained('lost_found_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('private_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lost_found_item_id');
        });
    }
};
