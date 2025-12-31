<?php

namespace App\Jobs\Aws;

use App\Models\ProvisionLog;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateDomainJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_VALIDATE_DOMAIN,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Validate domain format (basic check)
            if (!$this->isValidDomain($site->domain)) {
                throw new \Exception('Invalid domain format');
            }

            // Check if domain already exists in our system
            $existingSite = Site::where('domain', $site->domain)
                ->where('id', '!=', $site->id)
                ->where('status', '!=', Site::STATUS_DESTROYED)
                ->first();

            if ($existingSite) {
                throw new \Exception('Domain already exists in the system');
            }

            $log->markAsCompleted("Domain validation successful for: {$site->domain}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }

    /**
     * Validate domain format
     */
    private function isValidDomain(string $domain): bool
    {
        // More permissive regex that accepts domains like "example.com", "test.io", "sub.domain.test"
        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i',
            $domain
        );
    }
}
