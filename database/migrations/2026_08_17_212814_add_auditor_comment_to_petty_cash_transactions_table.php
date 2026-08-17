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
        if (Schema::hasColumn('petty_cash_transactions', 'auditor_comment')) {
            return;
        }

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->text('auditor_comment')->nullable()->after('auditor_approved_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropColumn('auditor_comment');
        });
    }
};
