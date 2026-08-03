<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('petty_cash_transactions', 'fund_id')) {
            return;
        }

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fund_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->foreignId('fund_id')->nullable()->after('id')->constrained('petty_cash_funds');
        });
    }
};
