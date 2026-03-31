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
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique(); // CMD-2026-0001
            $table->enum('status', ['en_attente', 'en_cours', 'livree', 'annulee'])->default('en_attente');
            $table->decimal('prix_total', 12, 2)->default(0);
            $table->foreignId('quotation_id')->constrained('quotations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('order')->default(0); // Pour le tri (last added first)
            $table->timestamps();

            // Index pour les recherches fréquentes
            $table->index(['user_id', 'status']);
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
