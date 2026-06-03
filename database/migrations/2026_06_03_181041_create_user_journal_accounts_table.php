<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_journal_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('cash_box_account_id')
                  ->nullable()
                  ->constrained('accounts')
                  ->nullOnDelete();
            $table->foreignId('bank_account_id')
                  ->nullable()
                  ->constrained('accounts')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_journal_accounts');
    }
};
