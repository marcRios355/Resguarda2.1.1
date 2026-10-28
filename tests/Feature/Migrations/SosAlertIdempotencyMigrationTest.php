<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('SOS idempotency rollback preserves alert states and refuses to discard keys', function () {
    $originalConnection = DB::getDefaultConnection();
    Config::set('database.connections.sos_migration_test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('sos_migration_test');
    DB::setDefaultConnection('sos_migration_test');

    try {
        Schema::create('sos_alerts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('delivery_status', 30);
            $table->uuid('idempotency_key')->nullable();
            $table->unique(['user_id', 'idempotency_key']);
        });

        DB::table('sos_alerts')->insert([
            ['user_id' => 1, 'delivery_status' => 'accepted', 'idempotency_key' => '00000000-0000-4000-8000-000000000001'],
            ['user_id' => 2, 'delivery_status' => 'uncertain', 'idempotency_key' => '00000000-0000-4000-8000-000000000002'],
            ['user_id' => 3, 'delivery_status' => 'registered', 'idempotency_key' => null],
            ['user_id' => 4, 'delivery_status' => 'pending', 'idempotency_key' => null],
        ]);
        $migration = require database_path('migrations/2026_10_28_010113_add_idempotency_key_to_sos_alerts_table.php');

        expect(fn () => $migration->down())
            ->toThrow(RuntimeException::class, 'Cannot roll back SOS idempotency');

        expect(DB::table('sos_alerts')->orderBy('id')->pluck('delivery_status')->all())
            ->toBe(['accepted', 'uncertain', 'registered', 'pending']);
        expect(Schema::hasColumn('sos_alerts', 'idempotency_key'))->toBeTrue();

        DB::table('sos_alerts')->update(['idempotency_key' => null]);
        $migration->down();

        expect(DB::table('sos_alerts')->orderBy('id')->pluck('delivery_status')->all())
            ->toBe(['accepted', 'uncertain', 'registered', 'pending']);
        expect(Schema::hasColumn('sos_alerts', 'idempotency_key'))->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('sos_migration_test');
    }
});
