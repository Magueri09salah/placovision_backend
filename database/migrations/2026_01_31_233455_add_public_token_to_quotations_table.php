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
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('status');
        });

        // Générer des tokens pour les devis existants
        \DB::table('quotations')->whereNull('public_token')->get()->each(function ($quotation) {
            \DB::table('quotations')
                ->where('id', $quotation->id)
                ->update(['public_token' => Str::random(32)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
