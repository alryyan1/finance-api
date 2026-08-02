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
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            // The Firebase Storage download URL Laravel last synced document_path from —
            // lets reconcileFromFirestore() detect a receipt attached/replaced via the
            // WhatsApp bot (which uploads straight to Firebase Storage) by comparing this
            // against the Firestore mirror doc's current document_url.
            $table->string('document_firebase_url', 2048)->nullable()->after('document_original_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropColumn('document_firebase_url');
        });
    }
};
