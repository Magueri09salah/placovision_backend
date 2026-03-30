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
            $table->unsignedBigInteger('odoo_order_id')->nullable()->after('reference');
            $table->string('odoo_order_name')->nullable()->after('odoo_order_id');
            $table->string('odoo_status')->nullable()->after('odoo_order_name');
            $table->timestamp('odoo_synced_at')->nullable()->after('odoo_status');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['odoo_order_id', 'odoo_order_name', 'odoo_status', 'odoo_synced_at']);
        });
    }
};
