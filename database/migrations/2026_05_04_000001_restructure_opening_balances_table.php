<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backup existing rows
        $existing = DB::table('opening_balances')->get();

        Schema::drop('opening_balances');

        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')
                ->nullable()
                ->constrained('fiscal_years')
                ->nullOnDelete();
            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete();
            $table->decimal('debit',  15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
            // (fiscal_year_id, account_id) uniqueness enforced at app level
            // because MySQL treats NULL != NULL in unique indexes
        });

        // Restore old rows with fiscal_year_id = null (legacy global balances)
        foreach ($existing as $row) {
            DB::table('opening_balances')->insert([
                'fiscal_year_id' => null,
                'account_id'     => $row->account_id,
                'debit'          => $row->debit,
                'credit'         => $row->credit,
                'created_at'     => $row->created_at,
                'updated_at'     => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        $existing = DB::table('opening_balances')
            ->whereNull('fiscal_year_id')
            ->get();

        Schema::drop('opening_balances');

        Schema::create('opening_balances', function (Blueprint $table) {
            $table->foreignId('account_id')->primary()->constrained('accounts')->cascadeOnDelete();
            $table->decimal('debit',  15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
        });

        foreach ($existing as $row) {
            DB::table('opening_balances')->insert([
                'account_id' => $row->account_id,
                'debit'      => $row->debit,
                'credit'     => $row->credit,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }
};
