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
        if (Schema::hasColumn('petty_cash_transactions', 'auditor_approved_at')) {
            return;
        }

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->timestamp('auditor_approved_at')->nullable()->after('manager_approved_by_user_id');
            $table->foreignId('auditor_approved_by_user_id')->nullable()->after('auditor_approved_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auditor_approved_by_user_id');
            $table->dropColumn('auditor_approved_at');
        });
    }
};
