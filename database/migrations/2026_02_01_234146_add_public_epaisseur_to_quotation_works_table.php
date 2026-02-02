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
            $table->string('epaisseur', 10)->nullable()->default('72')->after('work_type'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_works', function (Blueprint $table) {
            $table->dropColumn('epaisseur');
        });
    }
};
