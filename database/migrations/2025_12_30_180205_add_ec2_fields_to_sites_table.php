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
        Schema::table('sites', function (Blueprint $table) {
            // EC2 Instance information
            $table->string('instance_id')->nullable()->after('dns_record_id');
            $table->string('key_pair_name')->nullable()->after('instance_id');
            $table->text('private_key')->nullable()->after('key_pair_name'); // encrypted
            $table->string('security_group_id')->nullable()->after('private_key');
            $table->string('mysql_root_password')->nullable()->after('security_group_id'); // encrypted
            
            // Index for instance lookups
            $table->index('instance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex(['instance_id']);
            $table->dropColumn([
                'instance_id',
                'key_pair_name',
                'private_key',
                'security_group_id',
                'mysql_root_password',
            ]);
        });
    }
};
