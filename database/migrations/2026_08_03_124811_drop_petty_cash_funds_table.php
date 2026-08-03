<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No historical petty cash data needs to survive this refactor — wipe it
        // first so nothing is left holding a reference to petty_cash_funds. This
        // also sidesteps the case where a DB's `migrations` table doesn't
        // perfectly reflect its actual schema (e.g. a restored/diagnostic copy):
        // the fund_id foreign key from 2026_07_31_223945 can still be present
        // even though the 2026_08_03_124810 migration that's supposed to drop it
        // is recorded as already run, which made this DROP TABLE fail with a
        // parent-row constraint error.
        if (Schema::hasTable('petty_cash_transactions')) {
            DB::table('petty_cash_transactions')->truncate();
        }

        if (Schema::hasColumn('petty_cash_transactions', 'fund_id')) {
            Schema::table('petty_cash_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('fund_id');
            });
        }

        Schema::dropIfExists('petty_cash_funds');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('petty_cash_funds')) {
            return;
        }

        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('custodian_name');
            $table->foreignId('account_id')->constrained('accounts');
            $table->decimal('max_amount', 15, 2);
            $table->decimal('low_balance_threshold', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }
};
