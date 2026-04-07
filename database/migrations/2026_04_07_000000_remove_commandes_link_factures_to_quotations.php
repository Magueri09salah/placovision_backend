<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add quotation_id
        try {
            Schema::table('factures', function (Blueprint $table) {
                $table->unsignedBigInteger('quotation_id')->nullable()->after('date_emission');
            });
        } catch (\Throwable $e) {
            // Column already exists
        }

        // Step 2: Add user_id
        try {
            Schema::table('factures', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('quotation_id');
            });
        } catch (\Throwable $e) {
            // Column already exists
        }

        // Step 3: Add portal_url
        try {
            Schema::table('factures', function (Blueprint $table) {
                $table->string('portal_url')->nullable()->after('total');
            });
        } catch (\Throwable $e) {
            // Column already exists
        }

        // Step 4: Migrate existing data
        try {
            DB::statement('
                UPDATE factures
                INNER JOIN commandes ON factures.commande_id = commandes.id
                SET factures.quotation_id = commandes.quotation_id,
                    factures.user_id = commandes.user_id
            ');
        } catch (\Throwable $e) {
            // commande_id or commandes table may not exist anymore
        }

        // Step 5: Drop commande_id
        try {
            Schema::table('factures', function (Blueprint $table) {
                $table->dropForeign(['commande_id']);
            });
        } catch (\Throwable $e) {
            // Foreign key may not exist
        }

        try {
            Schema::table('factures', function (Blueprint $table) {
                $table->dropColumn('commande_id');
            });
        } catch (\Throwable $e) {
            // Column may not exist
        }

        // Step 6: Add foreign keys
        try {
            Schema::table('factures', function (Blueprint $table) {
                $table->foreign('quotation_id')->references('id')->on('quotations')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // FK already exists
        }

        try {
            Schema::table('factures', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // FK already exists
        }

        // Step 7: Drop commandes table
        Schema::dropIfExists('commandes');
    }

    public function down(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('en_attente');
            $table->decimal('prix_total', 12, 2)->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['quotation_id', 'user_id', 'portal_url']);
            $table->foreignId('commande_id')->nullable()->after('date_emission')->constrained('commandes')->cascadeOnDelete();
        });
    }
};