<?php

namespace App\Jobs;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class ProvisionWordPressSite implements ShouldQueue
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

        // Mark site as provisioning
        $site->markAsProvisioning();

        // Select jobs based on provisioning mode
        $jobs = match (config('provisioning.mode')) {
            'local' => $this->getLocalJobs($site),
            'aws' => $this->getAwsJobs($site),
            default => throw new \Exception('Invalid provisioning mode: ' . config('provisioning.mode')),
        };

        // Chain all provisioning jobs in sequence
        $siteId = $this->siteId;
        Bus::chain($jobs)->catch(function (\Throwable $e) use ($siteId) {
            // If any job fails, mark site as failed
            $site = Site::find($siteId);
            if ($site) {
                $site->markAsFailed();
            }
        })->dispatch();
    }

    private function getLocalJobs(Site $site): array
    {
        return [
            new Local\ValidateDomainJob($site->id),
            new Local\PrepareFilesystemJob($site->id),
            new Local\CreateDatabaseJob($site->id),
            new Local\InstallWordPressJob($site->id),
            new Local\ConfigureNginxJob($site->id),
            new ReloadNginxJob($site->id), // Same for both modes
            new Local\UpdateHostsJob($site->id),
            new Local\VerifySiteJob($site->id),
        ];
    }

    private function getAwsJobs(Site $site): array
    {
        return [
            new Aws\ValidateDomainJob($site),
            new Aws\PrepareFilesystemJob($site),
            new Aws\CreateDatabaseJob($site),
            new Aws\InstallWordPressJob($site),
            new Aws\ConfigureNginxJob($site),
            new ReloadNginxJob($site->id),
            new Aws\UpdateDnsJob($site),
            new Aws\VerifySiteJob($site),
        ];
    }
}
