<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            // SQLite (used in tests) has no rigid column typing; app-level
            // casts and validation already enforce integers there.
            return;
        }

        DB::statement('ALTER TABLE opening_balances MODIFY debit INT UNSIGNED NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE opening_balances MODIFY credit INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE opening_balances MODIFY debit DECIMAL(15,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE opening_balances MODIFY credit DECIMAL(15,2) NOT NULL DEFAULT 0');
    }
};
