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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();

        $table->string('reference')->unique(); // DEV-2026-0001

        // Infos client / chantier
        $table->string('client_name');
        $table->string('client_email')->nullable();
        $table->string('client_phone')->nullable();
        $table->string('site_address');
        $table->string('site_city');
        $table->date('work_date')->nullable();

        // Ouvrage
        $table->enum('work_type', [
            'cloison', 'plafond', 'doublage', 'habillage', 'autres'
        ]);

        // Calcul
        $table->json('measurements');        // surfaces, longueurs…
        $table->decimal('total_surface', 8, 2);
        $table->decimal('estimated_amount', 10, 2);

        // Annexe (DTU & hypothèses)
        $table->json('assumptions');

        // PDF
        $table->string('pdf_path')->nullable();

        // Statut simple
        $table->enum('status', ['draft'])->default('draft');

        $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
