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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->enum('status', [
                'pending',
                'provisioning',
                'live',
                'failed',
                'destroyed'
            ])->default('pending');
            
            // WordPress credentials (encrypted)
            $table->string('wp_admin_username');
            $table->text('wp_admin_password'); // encrypted
            $table->string('wp_admin_email');
            
            // Database credentials (encrypted)
            $table->string('db_name')->nullable();
            $table->string('db_username')->nullable();
            $table->text('db_password')->nullable(); // encrypted
            
            // Server information
            $table->string('ec2_path')->nullable(); // e.g., /var/www/example.com
            $table->string('public_ip')->nullable();
            
            // DNS information
            $table->string('dns_record_id')->nullable();
            
            // Timestamps
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('destroyed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
