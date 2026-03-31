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
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique(); // FAC-2026-0001
            $table->date('date_emission')->default(now());
            $table->foreignId('commande_id')->constrained('commandes')->onDelete('cascade');
            $table->enum('status', ['en_attente', 'payee', 'annulee'])->default('en_attente');
            $table->decimal('total', 12, 2)->default(0);
            $table->unsignedInteger('order')->default(0); // Pour le tri (last added first)
            $table->timestamps();

            // Index pour les recherches fréquentes
            $table->index('status');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
