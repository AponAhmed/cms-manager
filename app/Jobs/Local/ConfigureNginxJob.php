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
use Illuminate\Support\Facades\View;

class ConfigureNginxJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_CONFIGURE_NGINX,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Generate Nginx configuration
            $config = View::make('templates.nginx-vhost', [
                'domain' => $site->domain,
                'root_path' => $site->ec2_path . '/public',
                'logs_path' => $site->ec2_path . '/logs',
                'php_fpm_socket' => config('provisioning.local.php_fpm_socket'),
            ])->render();

            $sitesAvailable = config('provisioning.local.nginx.sites_available');
            $sitesEnabled = config('provisioning.local.nginx.sites_enabled');
            $configFile = $sitesAvailable . '/' . $site->domain . '.conf';
            
            // Write config file
            if (!$service->writeFile($configFile, $config)) {
                throw new \Exception('Failed to write Nginx configuration');
            }

            // Create symlink
            $symlinkCommand = sprintf(
                'ln -sf %s %s',
                $configFile,
                $sitesEnabled . '/' . $site->domain . '.conf'
            );
            
            $result = $service->executeSudo($symlinkCommand);
            
            if (!$result['success']) {
                throw new \Exception('Failed to create symlink: ' . $result['output']);
            }

            // Test Nginx configuration
            $result = $service->testNginxConfig();
            
            if (!$result['success']) {
                throw new \Exception('Nginx configuration test failed: ' . $result['output']);
            }

            $log->markAsCompleted('Nginx virtual host configured locally');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
