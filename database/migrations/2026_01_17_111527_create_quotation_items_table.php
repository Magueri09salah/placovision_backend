<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('quotation_id')->constrained()->onDelete('cascade');
            
            // Ligne devis
            $table->string('description'); // Description article
            $table->string('reference')->nullable(); // Référence produit
            $table->decimal('quantity', 10, 2); // Quantité
            $table->string('unit', 20); // Unité (m², ML, U)
            $table->decimal('unit_price_dh', 10, 2); // Prix unitaire DH
            $table->decimal('total_price_dh', 12, 2); // Total ligne DH
            
            // Catégorie
            $table->string('category')->nullable(); // plaques, rails, montants, accessoires
            
            $table->timestamps();
            
            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};