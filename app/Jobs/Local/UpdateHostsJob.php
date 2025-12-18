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

class UpdateHostsJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_UPDATE_DNS,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Add domain to /etc/hosts
            if (!$service->addToHosts($site->domain)) {
                throw new \Exception('Failed to add domain to /etc/hosts');
            }

            // Store local IP
            $site->update(['public_ip' => '127.0.0.1']);

            $log->markAsCompleted("Domain added to /etc/hosts: {$site->domain} -> 127.0.0.1");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
