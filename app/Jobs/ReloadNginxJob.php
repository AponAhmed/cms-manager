<?php

namespace App\Jobs;

use App\Models\ProvisionLog;
use App\Models\Site;
use App\Services\Aws\SshService;
use App\Services\Local\LocalProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReloadNginxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $siteId
    ) {}

    public function handle(SshService $ssh, LocalProvisioningService $local): void
    {
        $site = Site::find($this->siteId);
        if (!$site) {
            return;
        }
        $log = ProvisionLog::create([
            'site_id' => $site->id,
            'step' => ProvisionLog::STEP_RELOAD_NGINX,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            if (config('provisioning.mode') === 'local') {
                // Local mode
                $result = $local->reloadNginx();
                if (!$result['success']) {
                    throw new \Exception('Failed to reload Nginx locally: ' . $result['output']);
                }
                
                // Verify Nginx is running locally
                $result = $local->execute('systemctl is-active nginx');
            } else {
                // AWS/Remote mode
                $result = $ssh->reloadNginx();
                if (!$result['success']) {
                    throw new \Exception('Failed to reload Nginx: ' . $result['output']);
                }

                // Verify Nginx is running remotely
                $result = $ssh->execute('sudo systemctl is-active nginx');
            }
            
            if (!str_contains($result['output'], 'active')) {
                throw new \Exception('Nginx is not active after reload');
            }

            $log->markAsCompleted('Nginx reloaded successfully');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
