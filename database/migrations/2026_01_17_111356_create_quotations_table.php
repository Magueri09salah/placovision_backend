<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            
            // Ownership
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            
            // Référence devis
            $table->string('reference', 50)->unique(); // DEV-2026-0001
            
            // ÉTAPE 1 : Infos chantier
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('site_address');
            $table->string('site_city');
            $table->string('site_postal_code', 20)->nullable();
            $table->date('work_date')->nullable();
            $table->text('observations')->nullable();
            $table->string('plan_file')->nullable(); // Dépôt de plan
            
            // ÉTAPE 2 : Type d'ouvrage
            $table->enum('work_type', ['cloison', 'plafond', 'doublage', 'habillage', 'autres']); // Type d'ouvrage principal
            
            // ÉTAPE 3 : Mesures et calcul (JSON pour flexibilité)
            $table->json('measurements'); // Toutes les mesures saisies
            $table->decimal('total_surface', 10, 2)->default(0); // Surface totale m²
            $table->decimal('estimated_amount_dh', 12, 2)->default(0); // Montant estimé DH
            
            // Hypothèses et règles (annexe)
            $table->json('assumptions')->nullable(); // Paramètres utilisés (entraxe, types plaques, DTU)
            
            // Statut
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])->default('draft');
            
            // Dates
            $table->date('valid_until')->nullable(); // Date validité
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            
            // Export
            $table->timestamp('exported_pdf_at')->nullable();
            
            // Odoo sync (pour plus tard)
            $table->integer('odoo_quotation_id')->nullable();
            $table->timestamp('odoo_synced_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('company_id');
            $table->index('project_id');
            $table->index('reference');
            $table->index('status');
            $table->index('work_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};