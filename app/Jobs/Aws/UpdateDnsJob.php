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

class UpdateDnsJob implements ShouldQueue
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
            'step' => ProvisionLog::STEP_UPDATE_DNS,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Check if DNS should be skipped (local testing mode)
            $skipDns = config('provisioning.aws.skip_dns', true);
            $hostedZoneId = config('aws.route53.hosted_zone_id');

            if ($skipDns || empty($hostedZoneId)) {
                $log->markAsCompleted(
                    "DNS update skipped (using EC2 public IP).\n" .
                    "Access site at: http://{$site->public_ip}"
                );
                return;
            }

            // Create Route53 A record
            $route53 = new Route53Service();
            
            $changeId = $route53->createARecord(
                $site->domain,
                $site->public_ip,
                config('aws.route53.ttl', 300)
            );

            if (!$changeId) {
                throw new \Exception('Failed to create DNS record');
            }

            // Update site with change ID
            $site->update([
                'dns_record_id' => $changeId,
            ]);

            // Wait for DNS propagation (optional)
            $maxAttempts = config('aws.route53.max_wait_attempts', 30);
            $propagated = $route53->waitForChange($changeId, $maxAttempts);

            if ($propagated) {
                $log->markAsCompleted("DNS A record created and propagated: {$site->domain} -> {$site->public_ip}");
            } else {
                $log->markAsCompleted("DNS A record created (propagation pending): {$site->domain} -> {$site->public_ip}");
            }
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
