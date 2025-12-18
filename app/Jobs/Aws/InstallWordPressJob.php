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
        private Site $site
    ) {}

    public function handle(SshService $ssh): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_INSTALL_WORDPRESS,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $publicPath = $this->site->ec2_path . '/public';
            
            // Change to site directory and run WP-CLI commands
            $commands = [
                // Download WordPress
                "cd {$publicPath} && sudo -u www-data wp core download --version=" . config('wordpress.defaults.wp_version', 'latest'),
                
                // Create wp-config.php
                sprintf(
                    "cd {$publicPath} && sudo -u www-data wp config create --dbname='%s' --dbuser='%s' --dbpass='%s' --dbhost='%s'",
                    $this->site->db_name,
                    $this->site->db_username,
                    $this->site->db_password,
                    config('wordpress.mysql.host', 'localhost')
                ),
                
                // Install WordPress
                sprintf(
                    "cd {$publicPath} && sudo -u www-data wp core install --url='%s' --title='%s' --admin_user='%s' --admin_password='%s' --admin_email='%s' --skip-email",
                    $this->site->domain,
                    $this->site->domain,
                    $this->site->wp_admin_username,
                    $this->site->wp_admin_password,
                    $this->site->wp_admin_email
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
            $pluginsToRemove = config('wordpress.defaults.plugins_to_remove', ['akismet', 'hello']);
            foreach ($pluginsToRemove as $plugin) {
                $ssh->execute("cd {$publicPath} && sudo -u www-data wp plugin delete {$plugin} --quiet");
            }

            // Set default theme
            $theme = config('wordpress.defaults.theme', 'twentytwentyfour');
            $ssh->execute("cd {$publicPath} && sudo -u www-data wp theme activate {$theme}");

            // Disable pingbacks and trackbacks
            $ssh->execute("cd {$publicPath} && sudo -u www-data wp option update default_ping_status 0");
            $ssh->execute("cd {$publicPath} && sudo -u www-data wp option update default_pingback_flag 0");

            // Disable XML-RPC if configured
            if (config('wordpress.security.disable_xmlrpc', true)) {
                $ssh->execute("cd {$publicPath} && sudo -u www-data wp config set XMLRPC_ENABLED false --raw");
            }

            // Disable file editing if configured
            if (config('wordpress.security.disable_file_edit', true)) {
                $ssh->execute("cd {$publicPath} && sudo -u www-data wp config set DISALLOW_FILE_EDIT true --raw");
            }

            $log->markAsCompleted('WordPress installed successfully');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $this->site->markAsFailed();
            $this->fail($e);
        }
    }
}
