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
        Schema::create('external_party_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source_system', 50);
            $table->string('source_type', 50);
            $table->string('source_id', 50);
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['source_system', 'source_type', 'source_id'], 'external_party_mappings_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_party_mappings');
    }
};
