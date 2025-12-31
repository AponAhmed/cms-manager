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

class PrepareFilesystemJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_PREPARE_FILESYSTEM,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $ssh = new SshService($site);
            $sitePath = config('aws.wordpress.paths.base') . '/' . $site->domain;

            // Create main directory
            $result = $ssh->createDirectory($sitePath);
            
            if (!$result[0]['success']) {
                throw new \Exception('Failed to create site directory: ' . $result[0]['output']);
            }

            // Create public directory
            $publicPath = $sitePath . '/public';
            $result = $ssh->createDirectory($publicPath);
            
            if (!$result[0]['success']) {
                throw new \Exception('Failed to create public directory: ' . $result[0]['output']);
            }

            // Create logs directory
            $logsPath = $sitePath . '/logs';
            $result = $ssh->createDirectory($logsPath);
            
            if (!$result[0]['success']) {
                throw new \Exception('Failed to create logs directory: ' . $result[0]['output']);
            }

            // Update site with path
            $site->update(['ec2_path' => $sitePath]);

            $log->markAsCompleted("Filesystem prepared at: {$sitePath}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
