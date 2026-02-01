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
        Schema::table('quotation_works', function (Blueprint $table) {
            $table->decimal('longueur', 10, 2)->nullable()->after('work_type');
            $table->decimal('hauteur', 10, 2)->nullable()->after('longueur');
        });
         \DB::statement("ALTER TABLE quotation_works MODIFY COLUMN work_type ENUM('habillage_mur', 'plafond_ba13', 'cloison', 'cloison_simple', 'cloison_double', 'gaine_creuse', 'gaine_technique') NOT NULL");
        
        // Migrer les anciens types vers les nouveaux
        \DB::table('quotation_works')
            ->where('work_type', 'cloison')
            ->update(['work_type' => 'cloison_simple']);
            
        \DB::table('quotation_works')
            ->where('work_type', 'gaine_creuse')
            ->update(['work_type' => 'gaine_technique']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revenir aux anciens types
        \DB::table('quotation_works')
            ->where('work_type', 'cloison_simple')
            ->update(['work_type' => 'cloison']);
            
        \DB::table('quotation_works')
            ->where('work_type', 'cloison_double')
            ->update(['work_type' => 'cloison']);
            
        \DB::table('quotation_works')
            ->where('work_type', 'gaine_technique')
            ->update(['work_type' => 'gaine_creuse']);

        // Remettre l'ancien enum
        \DB::statement("ALTER TABLE quotation_works MODIFY COLUMN work_type ENUM('habillage_mur', 'plafond_ba13', 'cloison', 'gaine_creuse') NOT NULL");

        Schema::table('quotation_works', function (Blueprint $table) {
            $table->dropColumn(['longueur', 'hauteur']);
        });
    }
};
