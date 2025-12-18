<?php

namespace App\Jobs\Local;

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
            $domain = $site->domain;
            
            // Check domain format (simpler for local)
            if (!preg_match('/^[a-z0-9.-]+$/i', $domain)) {
                throw new \Exception('Invalid domain format');
            }

            // Must end with .test suffix for local
            if (!str_ends_with($domain, '.test')) {
                throw new \Exception('Local domains must end with .test suffix');
            }

            // Check if domain already exists
            $existingSite = Site::where('domain', $domain)
                ->where('id', '!=', $site->id)
                ->where('status', '!=', Site::STATUS_DESTROYED)
                ->first();

            if ($existingSite) {
                throw new \Exception('Domain already exists in the system');
            }

            $log->markAsCompleted("Domain validation successful (local mode): {$domain}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
