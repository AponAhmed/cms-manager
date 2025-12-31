<?php

namespace App\Jobs\Aws;

use App\Models\ProvisionLog;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class VerifySiteJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_VERIFY_SITE,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Wait a bit for everything to settle
            sleep(10);

            // Determine URL to check - use public IP for testing
            $skipDns = config('provisioning.aws.skip_dns', true);
            $url = $skipDns 
                ? "http://{$site->public_ip}" 
                : "http://{$site->domain}";

            // Try to access the site
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                throw new \Exception("Site is not accessible at {$url}. HTTP status: {$response->status()}");
            }

            // Check for WordPress indicators
            $body = $response->body();
            $isWordPress = str_contains($body, 'wp-content') ||
                          str_contains($body, 'wordpress') ||
                          str_contains($body, 'wp-includes');

            if (!$isWordPress) {
                throw new \Exception('Site is accessible but does not appear to be WordPress');
            }

            // Mark site as live
            $site->markAsLive();

            $accessUrl = $skipDns 
                ? "http://{$site->public_ip}" 
                : "http://{$site->domain}";
            
            $adminUrl = $skipDns
                ? "http://{$site->public_ip}/wp-admin"
                : "http://{$site->domain}/wp-admin";

            $log->markAsCompleted(
                "Site is live and accessible!\n" .
                "WordPress URL: {$accessUrl}\n" .
                "Admin URL: {$adminUrl}\n" .
                "Admin Username: {$site->wp_admin_username}"
            );
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
