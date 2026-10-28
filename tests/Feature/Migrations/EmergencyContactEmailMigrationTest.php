<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('emergency contact email rollback refuses to remove saved addresses', function () {
    $originalConnection = DB::getDefaultConnection();
    Config::set('database.connections.emergency_email_migration_test', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('emergency_email_migration_test');
    DB::setDefaultConnection('emergency_email_migration_test');

    try {
        Schema::create('emergency_contacts', function (Blueprint $table): void {
            $table->id();
            $table->text('email')->nullable();
        });
        DB::table('emergency_contacts')->insert(['email' => 'contact@example.test']);
        $migration = require database_path('migrations/2026_10_28_010051_add_email_to_emergency_contacts_table.php');

        expect(fn () => $migration->down())
            ->toThrow(RuntimeException::class, 'Cannot roll back emergency contact emails');
        expect(Schema::hasColumn('emergency_contacts', 'email'))->toBeTrue()
            ->and(DB::table('emergency_contacts')->value('email'))->toBe('contact@example.test');

        DB::table('emergency_contacts')->update(['email' => null]);
        $migration->down();

        expect(Schema::hasColumn('emergency_contacts', 'email'))->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('emergency_email_migration_test');
    }
});
