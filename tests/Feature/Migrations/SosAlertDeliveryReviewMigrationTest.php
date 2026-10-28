<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('SOS delivery review rollback refuses to discard review history', function () {
    $originalConnection = DB::getDefaultConnection();
    Config::set('database.connections.sos_review_migration_test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('sos_review_migration_test');
    DB::setDefaultConnection('sos_review_migration_test');

    try {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('sos_alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_outcome', 30)->nullable();
        });
        DB::table('users')->insert(['id' => 17]);
        DB::table('sos_alert_deliveries')->insert([
            'reviewed_at' => now(),
            'reviewed_by' => 17,
            'review_outcome' => 'accepted',
        ]);
        $migration = require database_path('migrations/2026_10_28_010115_add_review_fields_to_sos_alert_deliveries_table.php');

        expect(fn () => $migration->down())
            ->toThrow(RuntimeException::class, 'Cannot roll back SOS delivery review history');
        expect(Schema::hasColumn('sos_alert_deliveries', 'review_outcome'))->toBeTrue()
            ->and(DB::table('sos_alert_deliveries')->value('review_outcome'))->toBe('accepted');

        DB::table('sos_alert_deliveries')->update([
            'reviewed_at' => null,
            'reviewed_by' => null,
            'review_outcome' => null,
        ]);
        $migration->down();

        expect(Schema::hasColumn('sos_alert_deliveries', 'reviewed_at'))->toBeFalse()
            ->and(Schema::hasColumn('sos_alert_deliveries', 'reviewed_by'))->toBeFalse()
            ->and(Schema::hasColumn('sos_alert_deliveries', 'review_outcome'))->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('sos_review_migration_test');
    }
});
