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

class ReloadNginxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Site $site
    ) {}

    public function handle(SshService $ssh): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_RELOAD_NGINX,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Reload Nginx
            $result = $ssh->reloadNginx();
            
            if (!$result['success']) {
                throw new \Exception('Failed to reload Nginx: ' . $result['output']);
            }

            // Verify Nginx is running
            $result = $ssh->execute('sudo systemctl is-active nginx');
            
            if (!str_contains($result['output'], 'active')) {
                throw new \Exception('Nginx is not active after reload');
            }

            $log->markAsCompleted('Nginx reloaded successfully');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $this->site->markAsFailed();
            $this->fail($e);
        }
    }
}
