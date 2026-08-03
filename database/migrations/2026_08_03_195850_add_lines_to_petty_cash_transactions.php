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
            $table->foreignId('contra_account_id')->nullable()->change();
        });

        if (Schema::hasTable('petty_cash_transaction_lines')) {
            return;
        }

        Schema::create('petty_cash_transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contra_account_id')->constrained('accounts');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transaction_lines');

        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->foreignId('contra_account_id')->nullable(false)->change();
        });
    }
};
