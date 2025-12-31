<?php

namespace App\Jobs\Aws;

use App\Models\ProvisionLog;
use App\Models\Site;
use App\Services\Aws\SshService;
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

    public function handle(): void
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
            $ssh = new SshService($site);
            
            // Generate database name and user
            $dbName = 'wp_' . str_replace(['.', '-'], '_', $site->domain);
            $dbName = substr($dbName, 0, 64); // MySQL limit
            
            $dbUser = 'wp_' . Str::random(8);
            $dbPassword = Str::random(config('aws.security.min_password_length', 32));

            // Use the MySQL root password stored in the site
            $rootPassword = $site->mysql_root_password;

            // Create database
            $result = $ssh->executeMysql("CREATE DATABASE IF NOT EXISTS `{$dbName}`;", $rootPassword);
            
            if (!$result['success']) {
                throw new \Exception('Failed to create database: ' . $result['output']);
            }

            // Create user
            $result = $ssh->executeMysql(
                "CREATE USER '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPassword}';",
                $rootPassword
            );
            
            if (!$result['success']) {
                throw new \Exception('Failed to create database user: ' . $result['output']);
            }

            // Grant privileges
            $result = $ssh->executeMysql(
                "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost';",
                $rootPassword
            );
            
            if (!$result['success']) {
                throw new \Exception('Failed to grant privileges: ' . $result['output']);
            }

            // Flush privileges
            $result = $ssh->executeMysql("FLUSH PRIVILEGES;", $rootPassword);
            
            if (!$result['success']) {
                throw new \Exception('Failed to flush privileges: ' . $result['output']);
            }

            // Store credentials (encrypted automatically by model)
            $site->update([
                'db_name' => $dbName,
                'db_username' => $dbUser,
                'db_password' => $dbPassword,
            ]);

            $log->markAsCompleted("Database created: {$dbName}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
