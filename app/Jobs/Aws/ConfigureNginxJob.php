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
use Illuminate\Support\Facades\View;

class ConfigureNginxJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_CONFIGURE_NGINX,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $ssh = new SshService($site);
            
            // Determine server_name - use both public IP and domain
            $skipDns = config('provisioning.aws.skip_dns', true);
            $serverName = $skipDns 
                ? $site->public_ip 
                : "{$site->domain} {$site->public_ip}";
            
            // Generate Nginx config from template
            $config = View::make('templates.nginx-vhost', [
                'domain' => $site->domain,
                'server_name' => $serverName,
                'root_path' => $site->ec2_path . '/public',
                'logs_path' => $site->ec2_path . '/logs',
                'php_fpm_socket' => config('aws.wordpress.paths.php_fpm_socket'),
            ])->render();

            // Upload config to temp
            $uploadResult = $ssh->uploadContent($config, "/tmp/{$site->domain}.conf");
            
            if (!$uploadResult) {
                throw new \Exception('Failed to upload Nginx config');
            }

            // Move to sites-available with sudo
            $availablePath = config('aws.wordpress.paths.nginx_available') . '/' . $site->domain . '.conf';
            $result = $ssh->execute("sudo mv /tmp/{$site->domain}.conf {$availablePath}");
            
            if (!$result['success']) {
                throw new \Exception('Failed to move config to sites-available: ' . $result['output']);
            }

            // Create symlink to sites-enabled
            $enabledPath = config('aws.wordpress.paths.nginx_enabled') . '/' . $site->domain . '.conf';
            $result = $ssh->execute("sudo ln -sf {$availablePath} {$enabledPath}");
            
            if (!$result['success']) {
                throw new \Exception('Failed to create symlink: ' . $result['output']);
            }

            // Test Nginx configuration
            $result = $ssh->testNginxConfig();
            
            if (!$result['success']) {
                throw new \Exception('Nginx configuration test failed: ' . $result['output']);
            }

            // Reload Nginx
            $result = $ssh->reloadNginx();
            
            if (!$result['success']) {
                throw new \Exception('Failed to reload Nginx: ' . $result['output']);
            }

            $log->markAsCompleted('Nginx virtual host configured and reloaded');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
