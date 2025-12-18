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
        Schema::create('provision_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            
            $table->string('step'); // e.g., 'validate_domain', 'install_wordpress'
            $table->enum('status', [
                'pending',
                'running',
                'completed',
                'failed'
            ])->default('pending');
            
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['site_id', 'step']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provision_logs');
    }
};
