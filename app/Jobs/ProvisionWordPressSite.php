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
        private Site $site
    ) {}

    public function handle(): void
    {
        // Mark site as provisioning
        $this->site->markAsProvisioning();

        // Chain all provisioning jobs in sequence
        Bus::chain([
            new ValidateDomainJob($this->site),
            new PrepareFilesystemJob($this->site),
            new CreateDatabaseJob($this->site),
            new InstallWordPressJob($this->site),
            new ConfigureNginxJob($this->site),
            new ReloadNginxJob($this->site),
            new UpdateDnsJob($this->site),
            new VerifySiteJob($this->site),
        ])->catch(function (\Throwable $e) {
            // If any job fails, mark site as failed
            $this->site->markAsFailed();
        })->dispatch();
    }
}
