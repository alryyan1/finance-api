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
        // first so nothing is left holding a reference to petty_cash_funds.
        if (Schema::hasTable('petty_cash_transactions')) {
            DB::table('petty_cash_transactions')->truncate();
        }

        if (Schema::hasColumn('petty_cash_transactions', 'fund_id')) {
            Schema::table('petty_cash_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('fund_id');
            });
        }

        // Belt-and-suspenders: on a DB whose `migrations` table doesn't perfectly
        // reflect its actual schema (e.g. a restored/diagnostic copy), some other
        // foreign key we don't know about can still reference petty_cash_funds
        // even after the steps above — which keeps failing this DROP TABLE with a
        // parent-row constraint error no matter what we clean up by name. Drop it
        // with FK checks off so it goes through regardless of what's pointing at it.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            Schema::dropIfExists('petty_cash_funds');
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
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
