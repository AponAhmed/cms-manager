<?php

namespace App\Jobs\Aws;

use App\Models\Configuration;
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
        private Site $site
    ) {}

    public function handle(Route53Service $route53): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_UPDATE_DNS,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Get EC2 public IP
            $publicIp = Configuration::get(
                Configuration::KEY_EC2_PUBLIC_IP,
                config('wordpress.ec2.ip')
            );

            if (!$publicIp) {
                throw new \Exception('EC2 public IP not configured');
            }

            // Create A record
            $changeId = $route53->createARecord(
                $this->site->domain,
                $publicIp,
                config('wordpress.dns.ttl', 300)
            );

            if (!$changeId) {
                throw new \Exception('Failed to create DNS record');
            }

            // Update site with IP and change ID
            $this->site->update([
                'public_ip' => $publicIp,
                'dns_record_id' => $changeId,
            ]);

            // Wait for DNS propagation (optional, can be time-consuming)
            $maxAttempts = config('wordpress.dns.max_wait_attempts', 30);
            $propagated = $route53->waitForChange($changeId, $maxAttempts);

            if ($propagated) {
                $log->markAsCompleted("DNS A record created and propagated: {$this->site->domain} -> {$publicIp}");
            } else {
                $log->markAsCompleted("DNS A record created (propagation pending): {$this->site->domain} -> {$publicIp}");
            }
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $this->site->markAsFailed();
            $this->fail($e);
        }
    }
}
