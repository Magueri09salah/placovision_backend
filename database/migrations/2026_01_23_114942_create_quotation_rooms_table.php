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
        Schema::create('quotation_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->onDelete('cascade');
            
            // Type de pièce
            $table->enum('room_type', [
                'salon_sejour',
                'chambre', 
                'cuisine',
                'salle_de_bain',
                'wc',
                'bureau',
                'garage',
                'exterieur',
                'autre'
            ]);
            $table->string('room_name')->nullable(); // Nom personnalisé
            
            // Ordre d'affichage
            $table->integer('sort_order')->default(0);
            
            // Sous-total de la pièce
            $table->decimal('subtotal_ht', 12, 2)->default(0);
            
            $table->timestamps();
            
            $table->index('quotation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_rooms');
    }
};
