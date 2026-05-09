<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['receipt', 'payment']);
            $table->date('date');
            $table->string('reference', 50)->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'check'])->default('cash');
            $table->foreignId('cash_account_id')->constrained('accounts');
            $table->foreignId('contra_account_id')->constrained('accounts');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_vouchers');
    }
};
