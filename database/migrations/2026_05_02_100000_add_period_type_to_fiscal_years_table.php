<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->enum('period_type', ['yearly', 'monthly'])->default('yearly')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropColumn('period_type');
        });
    }
};
