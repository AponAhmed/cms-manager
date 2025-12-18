<?php

namespace App\Jobs;

use App\Models\ProvisionLog;
use App\Models\Site;
use App\Services\SshService;
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
        private Site $site
    ) {}

    public function handle(SshService $ssh): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_CONFIGURE_NGINX,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Generate Nginx config from template
            $config = View::make('templates.nginx-vhost', [
                'domain' => $this->site->domain,
                'root_path' => $this->site->ec2_path . '/public',
                'logs_path' => $this->site->ec2_path . '/logs',
                'php_fpm_socket' => config('wordpress.paths.php_fpm_socket'),
            ])->render();

            // Upload config to sites-available
            $availablePath = config('wordpress.paths.nginx_available') . '/' . $this->site->domain . '.conf';
            $uploadResult = $ssh->uploadContent($config, "/tmp/{$this->site->domain}.conf");
            
            if (!$uploadResult) {
                throw new \Exception('Failed to upload Nginx config');
            }

            // Move to sites-available with sudo
            $result = $ssh->execute("sudo mv /tmp/{$this->site->domain}.conf {$availablePath}");
            
            if (!$result['success']) {
                throw new \Exception('Failed to move config to sites-available: ' . $result['output']);
            }

            // Create symlink to sites-enabled
            $enabledPath = config('wordpress.paths.nginx_enabled') . '/' . $this->site->domain . '.conf';
            $result = $ssh->execute("sudo ln -sf {$availablePath} {$enabledPath}");
            
            if (!$result['success']) {
                throw new \Exception('Failed to create symlink: ' . $result['output']);
            }

            // Test Nginx configuration
            $result = $ssh->testNginxConfig();
            
            if (!$result['success']) {
                throw new \Exception('Nginx configuration test failed: ' . $result['output']);
            }

            $log->markAsCompleted('Nginx virtual host configured');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $this->site->markAsFailed();
            $this->fail($e);
        }
    }
}
