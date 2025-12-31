<?php

namespace App\Jobs\Aws;

use App\Models\ProvisionLog;
use App\Models\Site;
use App\Services\Aws\Ec2Service;
use App\Services\Aws\Route53Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DestroyWordPressSite implements ShouldQueue
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

        // Step 1: Terminate EC2 instance
        $this->terminateInstance($site);

        // Step 2: Delete key pair
        $this->deleteKeyPair($site);

        // Step 3: Delete DNS record (if exists)
        $this->deleteDnsRecord($site);

        // Mark site as destroyed
        $site->markAsDestroyed();
    }

    private function terminateInstance(Site $site): void
    {
        $log = ProvisionLog::create([
            'site_id' => $site->id,
            'step' => ProvisionLog::STEP_TERMINATE_INSTANCE,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            if (!$site->instance_id) {
                $log->markAsCompleted('No EC2 instance to terminate');
                return;
            }

            $ec2 = new Ec2Service();
            $ec2->terminateInstance($site->instance_id);

            $log->markAsCompleted("EC2 instance terminated: {$site->instance_id}");
        } catch (\Exception $e) {
            // Log but don't fail - we want to continue cleanup
            $log->markAsCompleted("Failed to terminate instance (non-critical): {$e->getMessage()}");
        }
    }

    private function deleteKeyPair(Site $site): void
    {
        try {
            if (!$site->key_pair_name) {
                return;
            }

            $ec2 = new Ec2Service();
            $ec2->deleteKeyPair($site->key_pair_name);
        } catch (\Exception $e) {
            // Ignore errors - key pair deletion is best effort
        }
    }

    private function deleteDnsRecord(Site $site): void
    {
        $log = ProvisionLog::create([
            'site_id' => $site->id,
            'step' => ProvisionLog::STEP_DELETE_DNS,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Skip if DNS was never configured
            $skipDns = config('provisioning.aws.skip_dns', true);
            $hostedZoneId = config('aws.route53.hosted_zone_id');

            if ($skipDns || empty($hostedZoneId) || !$site->public_ip) {
                $log->markAsCompleted('No DNS record to delete (DNS was not configured)');
                return;
            }

            $route53 = new Route53Service();
            $result = $route53->deleteRecord($site->domain, $site->public_ip);

            if ($result) {
                $log->markAsCompleted("Deleted DNS record for: {$site->domain}");
            } else {
                $log->markAsCompleted('DNS record not found or already deleted');
            }
        } catch (\Exception $e) {
            // Log but don't fail cleanup
            $log->markAsCompleted("DNS deletion error (non-critical): {$e->getMessage()}");
        }
    }
}
