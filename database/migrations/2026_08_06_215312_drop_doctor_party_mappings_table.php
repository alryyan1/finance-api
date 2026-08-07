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
        Schema::dropIfExists('doctor_party_mappings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('doctor_party_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('jawda_doctor_id')->unique();
            $table->foreignId('finance_party_id')
                ->nullable()
                ->constrained('parties')
                ->nullOnDelete();
            $table->timestamps();
        });
    }
};
