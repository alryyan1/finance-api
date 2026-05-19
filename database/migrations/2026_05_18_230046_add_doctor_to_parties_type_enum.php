<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE parties MODIFY COLUMN type ENUM('customer','supplier','employee','other','doctor') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE parties MODIFY COLUMN type ENUM('customer','supplier','employee','other') NOT NULL");
    }
};
