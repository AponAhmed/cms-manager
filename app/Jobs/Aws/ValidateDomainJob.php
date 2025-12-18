<?php

namespace App\Jobs\Aws;

use App\Models\ProvisionLog;
use App\Models\Site;
use App\Services\Aws\Route53Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Site $site
    ) {}

    public function handle(): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_VALIDATE_DOMAIN,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Validate domain format
            if (!Route53Service::isValidDomain($this->site->domain)) {
                throw new \Exception('Invalid domain format');
            }

            // Check if domain already exists in our system
            $existingSite = Site::where('domain', $this->site->domain)
                ->where('id', '!=', $this->site->id)
                ->where('status', '!=', Site::STATUS_DESTROYED)
                ->first();

            if ($existingSite) {
                throw new \Exception('Domain already exists in the system');
            }

            // Check if DNS record already exists
            $route53 = new Route53Service();
            if ($route53->recordExists($this->site->domain)) {
                throw new \Exception('DNS record already exists for this domain');
            }

            $log->markAsCompleted("Domain validation successful for: {$this->site->domain}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $this->site->markAsFailed();
            $this->fail($e);
        }
    }
}
