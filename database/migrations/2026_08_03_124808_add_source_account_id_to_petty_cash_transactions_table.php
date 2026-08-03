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
        if (Schema::hasColumn('petty_cash_transactions', 'source_account_id')) {
            return;
        }

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->foreignId('source_account_id')->nullable()->after('contra_account_id')
                ->constrained('accounts')->nullOnDelete();
        });

        // There was only ever one petty cash fund, so its account covers every
        // historical row — the transaction's credit side was always implicitly
        // that fund's account before source_account_id existed.
        if (Schema::hasTable('petty_cash_funds')) {
            $fundAccountId = DB::table('petty_cash_funds')->value('account_id');
            if ($fundAccountId) {
                DB::table('petty_cash_transactions')->update(['source_account_id' => $fundAccountId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_account_id');
        });
    }
};
