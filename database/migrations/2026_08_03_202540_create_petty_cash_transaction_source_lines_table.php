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
        Schema::create('petty_cash_transaction_source_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_transaction_id')
                ->constrained(indexName: 'pc_source_lines_transaction_id_fk')
                ->cascadeOnDelete();
            $table->foreignId('source_account_id')->constrained('accounts');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transaction_source_lines');
    }
};
