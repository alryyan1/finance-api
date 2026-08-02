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
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved'])->default('approved')->after('type');
            $table->timestamp('auditor_approved_at')->nullable()->after('description');
            $table->foreignId('auditor_approved_by_user_id')->nullable()->after('auditor_approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable()->after('auditor_approved_by_user_id');
            $table->foreignId('manager_approved_by_user_id')->nullable()->after('manager_approved_at')
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
            $table->dropConstrainedForeignId('manager_approved_by_user_id');
            $table->dropColumn(['status', 'auditor_approved_at', 'manager_approved_at']);
        });
    }
};
