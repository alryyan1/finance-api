<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite has no native ENUM type (columns are untyped TEXT), so there is nothing
        // to alter there — this only applies to MySQL's real ENUM constraint.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE parties MODIFY COLUMN type ENUM('customer','supplier','employee','other','doctor') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE parties MODIFY COLUMN type ENUM('customer','supplier','employee','other') NOT NULL");
    }
};
