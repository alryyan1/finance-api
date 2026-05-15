<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('petty_cash_replenishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('requested_by');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_replenishments');
    }
};
