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
        if (Schema::hasTable('petty_cash_transactions')) {
            return;
        }

        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('petty_cash_funds');
            $table->enum('type', ['expense', 'replenishment']);
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->enum('category', [
                'transportation', 'stationery', 'hospitality', 'maintenance', 'utilities', 'other',
            ])->nullable();
            $table->foreignId('contra_account_id')->constrained('accounts');
            $table->text('description')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transactions');
    }
};
