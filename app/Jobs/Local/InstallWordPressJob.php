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

class InstallWordPressJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_INSTALL_WORDPRESS,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $publicPath = $site->ec2_path . '/public';
            
            // Download WordPress
            $result = $service->execute("cd {$publicPath} && wp core download --allow-root");
            if (!$result['success']) {
                throw new \Exception('Failed to download WordPress: ' . $result['output']);
            }

            // Create wp-config.php
            $result = $service->execute(sprintf(
                "cd {$publicPath} && wp config create --dbname='%s' --dbuser='%s' --dbpass='%s' --dbhost='%s' --allow-root",
                $site->db_name,
                $site->db_username,
                $site->db_password,
                config('provisioning.local.mysql.host', '127.0.0.1')
            ));
            
            if (!$result['success']) {
                throw new \Exception('Failed to create wp-config: ' . $result['output']);
            }

            // Install WordPress
            $result = $service->execute(sprintf(
                "cd {$publicPath} && wp core install --url='http://%s' --title='%s' --admin_user='%s' --admin_password='%s' --admin_email='%s' --skip-email --allow-root",
                $site->domain,
                $site->domain,
                $site->wp_admin_username,
                $site->wp_admin_password,
                $site->wp_admin_email
            ));
            
            if (!$result['success']) {
                throw new \Exception('Failed to install WordPress: ' . $result['output']);
            }

            // Remove default plugins
            $service->execute("cd {$publicPath} && wp plugin delete akismet hello --allow-root");

            // Set default theme
            $service->execute("cd {$publicPath} && wp theme activate twentytwentyfour --allow-root");

            // Disable pingbacks
            $service->execute("cd {$publicPath} && wp option update default_ping_status 0 --allow-root");
            $service->execute("cd {$publicPath} && wp option update default_pingback_flag 0 --allow-root");

            // Security settings
            $service->execute("cd {$publicPath} && wp config set XMLRPC_ENABLED false --raw --allow-root");
            $service->execute("cd {$publicPath} && wp config set DISALLOW_FILE_EDIT true --raw --allow-root");

            $log->markAsCompleted('WordPress installed locally via WP-CLI');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
