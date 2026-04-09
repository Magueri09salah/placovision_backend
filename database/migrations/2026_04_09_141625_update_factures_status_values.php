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
            DB::statement("ALTER TABLE factures MODIFY COLUMN status ENUM('en_attente','non_payee','partielle','en_cours','payee','annulee') DEFAULT 'non_payee'");
            DB::table('factures')->where('status', 'en_attente')->update(['status' => 'non_payee']);
        }

        public function down(): void
        {
            DB::table('factures')->where('status', 'non_payee')->update(['status' => 'en_attente']);
            DB::statement("ALTER TABLE factures MODIFY COLUMN status ENUM('en_attente','payee','annulee') DEFAULT 'en_attente'");
        }
};
