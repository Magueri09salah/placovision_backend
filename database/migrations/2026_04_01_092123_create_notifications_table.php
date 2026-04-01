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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type'); // odoo_sent, odoo_sale, odoo_cancel, commande_created
            $table->string('title');
            $table->text('message');
            $table->string('icon')->nullable(); // emoji ou icon name
            $table->string('link')->nullable(); // URL vers la ressource
            $table->json('data')->nullable(); // Données supplémentaires (quotation_id, commande_id, etc.)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Index pour les recherches fréquentes
            $table->index(['user_id', 'read_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
