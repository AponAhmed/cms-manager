<?php

namespace App\Jobs\Aws;

use App\Models\ProvisionLog;
use App\Models\Site;
use App\Services\Aws\Ec2Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LaunchEc2InstanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    public function __construct(
        private int $siteId
    ) {}

    public function handle(Ec2Service $ec2): void
    {
        $site = Site::find($this->siteId);
        
        if (!$site) {
            return;
        }

        $log = ProvisionLog::create([
            'site_id' => $site->id,
            'step' => ProvisionLog::STEP_LAUNCH_INSTANCE,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            // Step 1: Launch EC2 instance
            $log->update(['output' => 'Launching EC2 instance...']);
            
            $instanceData = $ec2->launchInstance($site);

            // Store instance data in site
            $site->update([
                'instance_id' => $instanceData['instance_id'],
                'key_pair_name' => $instanceData['key_pair_name'],
                'private_key' => $instanceData['private_key'],
                'security_group_id' => $instanceData['security_group_id'],
                'mysql_root_password' => $instanceData['mysql_root_password'],
            ]);

            $log->update(['output' => "EC2 instance launched: {$instanceData['instance_id']}. Waiting for instance to be ready..."]);

            // Step 2: Wait for instance to be running and get public IP
            $instanceInfo = $ec2->waitForInstanceReady($instanceData['instance_id']);

            $site->update([
                'public_ip' => $instanceInfo['public_ip'],
                'ec2_path' => config('aws.wordpress.paths.base') . '/' . $site->domain,
            ]);

            $log->update(['output' => "Instance ready. Public IP: {$instanceInfo['public_ip']}. Waiting for SSH..."]);

            // Step 3: Wait for SSH to be available
            $sshReady = $ec2->waitForSshReady($instanceInfo['public_ip']);

            if (!$sshReady) {
                throw new \Exception('SSH did not become available within timeout');
            }

            // Wait additional time for user data script to complete
            $log->update(['output' => 'SSH ready. Waiting for server setup to complete...']);
            sleep(60); // Give time for user data script to finish

            $log->markAsCompleted(
                "EC2 instance launched successfully.\n" .
                "Instance ID: {$instanceData['instance_id']}\n" .
                "Public IP: {$instanceInfo['public_ip']}"
            );
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            $site->markAsFailed();
            $this->fail($e);
        }
    }
}
