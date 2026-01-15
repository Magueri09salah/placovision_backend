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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            // Company Info
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('ice', 50)->unique()->nullable();
            
            // Contact
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();
            
            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('MA');
            
            // Branding
            $table->string('logo')->nullable();
            $table->string('primary_color', 7)->default('#9E3E37');
            
            // Odoo Integration
            $table->integer('odoo_partner_id')->nullable();
            $table->timestamp('odoo_synced_at')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('ice');
            $table->index('odoo_partner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
