<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('SOS payload snapshot rollback refuses to remove stored retry content', function () {
    $originalConnection = DB::getDefaultConnection();
    Config::set('database.connections.sos_payload_migration_test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('sos_payload_migration_test');
    DB::setDefaultConnection('sos_payload_migration_test');

    try {
        Schema::create('sos_alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->text('request_payload')->nullable();
        });
        DB::table('sos_alert_deliveries')->insert([
            'request_payload' => 'encrypted-snapshot',
        ]);
        $migration = require database_path('migrations/2026_10_28_010114_add_request_payload_to_sos_alert_deliveries_table.php');

        expect(fn () => $migration->down())
            ->toThrow(RuntimeException::class, 'Cannot roll back SOS email payload snapshots');
        expect(Schema::hasColumn('sos_alert_deliveries', 'request_payload'))->toBeTrue();

        DB::table('sos_alert_deliveries')->update(['request_payload' => null]);
        $migration->down();

        expect(Schema::hasColumn('sos_alert_deliveries', 'request_payload'))->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('sos_payload_migration_test');
    }
});
