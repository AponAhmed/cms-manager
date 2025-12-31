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

class InstallWordPressJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_INSTALL_WORDPRESS,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $ssh = new SshService($site);
            $publicPath = $site->ec2_path . '/public';
            
            // Determine the WordPress URL
            // Use public IP if APP_ENV is local (testing), otherwise use domain
            $skipDns = config('provisioning.aws.skip_dns', true);
            $wpUrl = $skipDns ? $site->public_ip : $site->domain;
            
            // Change to site directory and run WP-CLI commands
            $commands = [
                // Download WordPress
                "cd {$publicPath} && sudo -u nginx wp core download --version=" . config('aws.wordpress.version', 'latest'),
                
                // Create wp-config.php
                sprintf(
                    "cd {$publicPath} && sudo -u nginx wp config create --dbname='%s' --dbuser='%s' --dbpass='%s' --dbhost='%s'",
                    $site->db_name,
                    $site->db_username,
                    $site->db_password,
                    'localhost'
                ),
                
                // Install WordPress
                sprintf(
                    "cd {$publicPath} && sudo -u nginx wp core install --url='%s' --title='%s' --admin_user='%s' --admin_password='%s' --admin_email='%s' --skip-email",
                    $wpUrl,
                    $site->domain,
                    $site->wp_admin_username,
                    $site->wp_admin_password,
                    $site->wp_admin_email
                ),
            ];

            // Execute download, config, and install
            foreach ($commands as $command) {
                $result = $ssh->execute($command);
                
                if (!$result['success']) {
                    throw new \Exception('WP-CLI command failed: ' . $result['output']);
                }
            }

            // Remove default plugins
            $pluginsToRemove = config('aws.wordpress.plugins_to_remove', ['akismet', 'hello']);
            foreach ($pluginsToRemove as $plugin) {
                $ssh->execute("cd {$publicPath} && sudo -u nginx wp plugin delete {$plugin} --quiet");
            }

            // Set default theme
            $theme = config('aws.wordpress.theme', 'twentytwentyfour');
            $ssh->execute("cd {$publicPath} && sudo -u nginx wp theme activate {$theme}");

            // Disable pingbacks and trackbacks
            $ssh->execute("cd {$publicPath} && sudo -u nginx wp option update default_ping_status 0");
            $ssh->execute("cd {$publicPath} && sudo -u nginx wp option update default_pingback_flag 0");

            // Disable XML-RPC if configured
            if (config('aws.security.disable_xmlrpc', true)) {
                $ssh->execute("cd {$publicPath} && sudo -u nginx wp config set XMLRPC_ENABLED false --raw");
            }

            // Disable file editing if configured
            if (config('aws.security.disable_file_edit', true)) {
                $ssh->execute("cd {$publicPath} && sudo -u nginx wp config set DISALLOW_FILE_EDIT true --raw");
            }

            $log->markAsCompleted('WordPress installed successfully');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
