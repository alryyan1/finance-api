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
        if (Schema::hasColumn('petty_cash_transactions', 'finance_manager_approved_at')) {
            return;
        }

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->timestamp('finance_manager_approved_at')->nullable()->after('auditor_comment');
            $table->foreignId('finance_manager_approved_by_user_id')->nullable()->after('finance_manager_approved_at')
                ->constrained('users', 'id', 'pct_finance_manager_approved_by_fk')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropForeign('pct_finance_manager_approved_by_fk');
            $table->dropColumn(['finance_manager_approved_by_user_id', 'finance_manager_approved_at']);
        });
    }
};
