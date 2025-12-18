<?php

namespace App\Jobs\Local;

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
            $url = 'http://' . $site->domain;

            // Try to access the site
            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                throw new \Exception('Site returned HTTP ' . $response->status());
            }

            $body = $response->body();

            // Check for WordPress indicators
            if (!str_contains($body, 'wp-content') && !str_contains($body, 'WordPress')) {
                throw new \Exception('WordPress site verification failed - site does not appear to be WordPress');
            }

            // Mark site as live
            $site->markAsLive();

            $log->markAsCompleted("Local site verified and accessible at: {$url}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
