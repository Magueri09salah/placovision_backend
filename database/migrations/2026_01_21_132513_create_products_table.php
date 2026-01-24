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
        Schema::create('products', function (Blueprint $table) {
                $table->id();
            
            $table->foreignId('subcategory_id')->constrained('product_subcategories')->onDelete('cascade');
            
            // Product Info
            $table->string('name'); // Full product name
            $table->string('sku')->unique(); // pf-12.5, ph-15, om-48, etc.
            $table->string('slug')->unique(); // plaque-feu-15mm
            
            // Specifications
            $table->string('thickness')->nullable(); // 12.5 mm, 15 mm, etc.
            $table->string('dimensions')->nullable(); // (2000–3000) × 1200 mm, 3000 mm, etc.
            
            // Pricing
            $table->decimal('price', 10, 2); // Unit price
            $table->string('unit'); // m², unité, rouleau, panneau, sac, boîte
            
            // Coverage (for auto-calculation)
            $table->decimal('coverage_per_piece', 10, 2)->nullable(); // Surface covered per piece
            
            // Description & Media
            $table->text('description')->nullable();
            $table->string('image')->nullable(); // ✅ AJOUTÉ : Product image
            
            // Stock Management
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_alert')->default(10);
            
            // Display
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false); // Produit vedette
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('subcategory_id');
            $table->index('sku');
            $table->index('slug');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('sort_order');
            $table->index('price');
            $table->fullText(['name', 'description']); // Search
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
