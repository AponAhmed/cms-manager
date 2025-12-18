<?php

namespace App\Jobs\Local;

use App\Models\Site;
use App\Services\Local\LocalProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DestroyWordPressSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $siteId
    ) {}

    public function handle(LocalProvisioningService $service): void
    {
        $site = Site::find($this->siteId);
        
        // If site not found, maybe it's already deleted, but we should log it
        if (!$site) {
            Log::warning("Site not found for destruction: {$this->siteId}");
            return;
        }

        Log::info("Starting local cleanup for site: {$site->domain}");

        try {
            // Step 1: Remove Nginx configuration
            $this->removeNginxConfig($service, $site);

            // Step 2: Reload Nginx
            $this->reloadNginx($service);

            // Step 3: Delete WordPress files
            $this->deleteWordPressFiles($service, $site);

            // Step 4: Drop database and user
            $this->dropDatabase($service, $site);

            // Step 5: Remove from /etc/hosts
            $this->removeFromHosts($service, $site);

            // Mark site as destroyed
            $site->update([
                'status' => Site::STATUS_DESTROYED,
                'destroyed_at' => now(),
            ]);

            Log::info("Local cleanup completed for site: {$site->domain}");
        } catch (\Exception $e) {
            Log::error("Local cleanup failed for site {$site->domain}: " . $e->getMessage());
            throw $e;
        }
    }

    private function removeNginxConfig(LocalProvisioningService $service, Site $site): void
    {
        Log::info("Removing Nginx configuration");

        $sitesAvailable = config('provisioning.local.nginx.sites_available');
        $sitesEnabled = config('provisioning.local.nginx.sites_enabled');

        // Remove symlink
        $service->deleteFile($sitesEnabled . '/' . $site->domain . '.conf');

        // Remove config file
        $service->deleteFile($sitesAvailable . '/' . $site->domain . '.conf');

        Log::info("Nginx configuration removed");
    }

    private function reloadNginx(LocalProvisioningService $service): void
    {
        Log::info("Reloading Nginx");

        $result = $service->reloadNginx();

        if (!$result['success']) {
            Log::warning("Nginx reload failed: " . $result['output']);
        } else {
            Log::info("Nginx reloaded successfully");
        }
    }

    private function deleteWordPressFiles(LocalProvisioningService $service, Site $site): void
    {
        if (!$site->ec2_path) {
            Log::info("No WordPress files to delete (path not set)");
            return;
        }

        Log::info("Deleting WordPress files: {$site->ec2_path}");

        $result = $service->deleteDirectory($site->ec2_path);

        if (!$result['success']) {
            Log::warning("Failed to delete WordPress files: " . $result['output']);
        } else {
            Log::info("WordPress files deleted");
        }
    }

    private function dropDatabase(LocalProvisioningService $service, Site $site): void
    {
        if (!$site->db_name || !$site->db_username) {
            Log::info("No database to drop (credentials not set)");
            return;
        }

        Log::info("Dropping database: {$site->db_name}");

        // Drop database
        $result = $service->executeMysql("DROP DATABASE IF EXISTS `{$site->db_name}`;");
        
        if (!$result['success']) {
            Log::warning("Failed to drop database: " . $result['output']);
        }

        // Drop user
        $result = $service->executeMysql("DROP USER IF EXISTS '{$site->db_username}'@'localhost';");
        
        if (!$result['success']) {
            Log::warning("Failed to drop user: " . $result['output']);
        } else {
            Log::info("Database and user dropped");
        }
    }

    private function removeFromHosts(LocalProvisioningService $service, Site $site): void
    {
        Log::info("Removing domain from /etc/hosts");

        if (!$service->removeFromHosts($site->domain)) {
            Log::warning("Failed to remove domain from /etc/hosts");
        } else {
            Log::info("Domain removed from /etc/hosts");
        }
    }
}
