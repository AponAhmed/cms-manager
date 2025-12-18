<?php

namespace App\Jobs\Local;

use App\Models\ProvisionLog;
use App\Models\Site;
use App\Services\Local\LocalProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CreateDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $siteId
    ) {}

    public function handle(LocalProvisioningService $service): void
    {
        $site = Site::find($this->siteId);
        if (!$site) {
            return;
        }
        $log = ProvisionLog::create([
            'site_id' => $site->id,
            'step' => ProvisionLog::STEP_CREATE_DATABASE,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Generate database name and user
            $dbName = 'wp_' . str_replace(['.', '-'], '_', $site->domain);
            $dbName = substr($dbName, 0, 64); // MySQL limit
            
            $dbUser = 'wp_' . Str::random(8);
            $dbPassword = Str::random(32);

            // Create database
            $result = $service->executeMysql("CREATE DATABASE IF NOT EXISTS `{$dbName}`;");
            
            if (!$result['success']) {
                throw new \Exception('Failed to create database: ' . $result['output']);
            }

            // Create user
            $result = $service->executeMysql(
                "CREATE USER '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPassword}';"
            );
            
            if (!$result['success']) {
                throw new \Exception('Failed to create database user: ' . $result['output']);
            }

            // Grant privileges
            $result = $service->executeMysql(
                "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost';"
            );
            
            if (!$result['success']) {
                throw new \Exception('Failed to grant privileges: ' . $result['output']);
            }

            // Flush privileges
            $result = $service->executeMysql("FLUSH PRIVILEGES;");
            
            if (!$result['success']) {
                throw new \Exception('Failed to flush privileges: ' . $result['output']);
            }

            // Store credentials
            $site->update([
                'db_name' => $dbName,
                'db_username' => $dbUser,
                'db_password' => $dbPassword,
            ]);

            $log->markAsCompleted("Local database created: {$dbName}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
