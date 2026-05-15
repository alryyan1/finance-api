<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('custodian_name');
            $table->foreignId('account_id')->constrained('accounts');          // حساب الصندوق الصغير
            $table->foreignId('bank_account_id')->constrained('accounts');     // حساب البنك/الصندوق الرئيسي
            $table->decimal('max_amount', 15, 2)->default(1000);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('low_balance_threshold', 15, 2)->default(200);    // تنبيه عند هذا الرصيد
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_funds');
    }
};
