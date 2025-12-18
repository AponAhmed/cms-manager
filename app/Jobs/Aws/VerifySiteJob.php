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
        private Site $site
    ) {}

    public function handle(): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_VERIFY_SITE,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Wait a bit for DNS to propagate
            sleep(10);

            // Try to access the site
            $response = Http::timeout(30)->get("http://{$this->site->domain}");

            if (!$response->successful()) {
                throw new \Exception("Site is not accessible. HTTP status: {$response->status()}");
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
            $this->site->markAsLive();

            $log->markAsCompleted("Site is live and accessible at: http://{$this->site->domain}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $this->site->markAsFailed();
            $this->fail($e);
        }
    }
}
