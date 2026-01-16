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
        Schema::create('projects', function (Blueprint $table) {
            // Ownership
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            
            // Project Info
            $table->string('name');
            $table->string('reference', 50)->unique()->nullable();
            $table->text('description')->nullable();
            
            // Location
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            
            // Dates
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('estimated_completion_date')->nullable();
            
            // Status
            $table->enum('status', ['draft', 'active', 'on_hold', 'completed', 'cancelled'])->default('draft');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            // Budget
            $table->decimal('estimated_budget', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->default(0);
            
            // Client Info (pour professionnels)
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone', 20)->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('company_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('start_date');
            $table->fullText(['name', 'description', 'client_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
