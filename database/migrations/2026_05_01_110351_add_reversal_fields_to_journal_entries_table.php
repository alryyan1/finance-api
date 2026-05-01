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
        Schema::table('journal_entries', function (Blueprint $table) {
            // Points to the reversal entry that was created for this entry
            $table->unsignedBigInteger('reversed_by')->nullable()->after('is_posted');
            // Points to the original entry this entry reverses
            $table->unsignedBigInteger('reversal_of')->nullable()->after('is_posted');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['reversed_by', 'reversal_of']);
        });
    }
};
