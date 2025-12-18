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

class PrepareFilesystemJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_PREPARE_FILESYSTEM,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $sitePath = config('provisioning.local.wordpress_base') . '/' . $site->domain;

            // Create main directory
            $result = $service->createDirectory($sitePath);
            
            if (!$result[0]['success']) {
                throw new \Exception('Failed to create site directory: ' . $result[0]['output']);
            }

            // Create public directory
            $publicPath = $sitePath . '/public';
            $result = $service->createDirectory($publicPath);
            
            if (!$result[0]['success']) {
                throw new \Exception('Failed to create public directory: ' . $result[0]['output']);
            }

            // Create logs directory
            $logsPath = $sitePath . '/logs';
            $result = $service->createDirectory($logsPath);
            
            if (!$result[0]['success']) {
                throw new \Exception('Failed to create logs directory: ' . $result[0]['output']);
            }

            // Update site with path
            $site->update(['ec2_path' => $sitePath]);

            $log->markAsCompleted("Local filesystem prepared at: {$sitePath}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
