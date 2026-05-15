<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('petty_cash_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->string('requester_name');
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->enum('category', [
                'transportation', 'stationery', 'hospitality',
                'maintenance', 'utilities', 'other',
            ]);
            $table->string('description');
            $table->string('reference')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts'); // حساب المصروف
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_requests');
    }
};
