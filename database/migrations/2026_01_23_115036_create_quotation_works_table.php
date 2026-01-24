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
        Schema::create('quotation_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_room_id')->constrained()->onDelete('cascade');
            
            // Type de travail
            $table->enum('work_type', [
                'habillage_mur',
                'plafond_ba13',
                'cloison',
                'gaine_creuse'
            ]);
            
            // Surface / Métrage
            $table->decimal('surface', 10, 2); // m² ou mètres linéaires
            $table->enum('unit', ['m2', 'ml']); // m² ou mètre linéaire
            
            // Sous-total du travail
            $table->decimal('subtotal_ht', 12, 2)->default(0);
            
            // Ordre
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            $table->index('quotation_room_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_works');
    }
};
