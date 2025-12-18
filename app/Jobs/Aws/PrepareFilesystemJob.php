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
        private Site $site
    ) {}

    public function handle(SshService $ssh): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_PREPARE_FILESYSTEM,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $sitePath = config('wordpress.paths.base') . '/' . $this->site->domain;

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
            $this->site->update(['ec2_path' => $sitePath]);

            $log->markAsCompleted("Filesystem prepared at: {$sitePath}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $this->site->markAsFailed();
            $this->fail($e);
        }
    }
}
