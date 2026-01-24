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
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_work_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            
            // Description du produit
            $table->string('designation');
            $table->text('description')->nullable();
            
            // Quantités
            $table->decimal('quantity_calculated', 10, 2); // Quantité calculée automatiquement
            $table->decimal('quantity_adjusted', 10, 2); // Quantité ajustée par l'utilisateur
            $table->string('unit', 20); // unité, m², rouleau, kg, etc.
            
            // Prix
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_ht', 12, 2);
            
            // Flag si modifié
            $table->boolean('is_modified')->default(false);
            
            // Ordre
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            $table->index('quotation_work_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
