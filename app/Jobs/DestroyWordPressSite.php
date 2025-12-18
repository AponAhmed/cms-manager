<?php

namespace App\Jobs;

use App\Models\ProvisionLog;
use App\Models\Site;
use App\Services\Route53Service;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DestroyWordPressSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Site $site
    ) {}

    public function handle(SshService $ssh, Route53Service $route53): void
    {
        // Step 1: Remove Nginx configuration
        $this->removeNginxConfig($ssh);

        // Step 2: Reload Nginx
        $this->reloadNginx($ssh);

        // Step 3: Delete WordPress files
        $this->deleteWordPressFiles($ssh);

        // Step 4: Drop database and user
        $this->dropDatabase($ssh);

        // Step 5: Delete DNS record
        $this->deleteDnsRecord($route53);

        // Mark site as destroyed
        $this->site->markAsDestroyed();
    }

    private function removeNginxConfig(SshService $ssh): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_REMOVE_NGINX,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $enabledPath = config('wordpress.paths.nginx_enabled') . '/' . $this->site->domain . '.conf';
            $availablePath = config('wordpress.paths.nginx_available') . '/' . $this->site->domain . '.conf';

            // Remove symlink
            $ssh->deleteFile($enabledPath);

            // Remove config file
            $ssh->deleteFile($availablePath);

            $log->markAsCompleted('Nginx configuration removed');
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function reloadNginx(SshService $ssh): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_RELOAD_NGINX,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            $result = $ssh->reloadNginx();

            if (!$result['success']) {
                // Log warning but don't fail the cleanup
                $log->markAsCompleted('Nginx reload failed (non-critical): ' . $result['output']);
            } else {
                $log->markAsCompleted('Nginx reloaded');
            }
        } catch (\Exception $e) {
            $log->markAsCompleted('Nginx reload error (non-critical): ' . $e->getMessage());
        }
    }

    private function deleteWordPressFiles(SshService $ssh): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_DELETE_FILES,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            if ($this->site->ec2_path) {
                $result = $ssh->deleteDirectory($this->site->ec2_path);

                if (!$result['success']) {
                    throw new \Exception('Failed to delete files: ' . $result['output']);
                }

                $log->markAsCompleted("Deleted directory: {$this->site->ec2_path}");
            } else {
                $log->markAsCompleted('No filesystem path to delete');
            }
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function dropDatabase(SshService $ssh): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_DROP_DATABASE,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            if ($this->site->db_name && $this->site->db_username) {
                // Drop database
                $result = $ssh->executeMysql("DROP DATABASE IF EXISTS `{$this->site->db_name}`;");

                if (!$result['success']) {
                    throw new \Exception('Failed to drop database: ' . $result['output']);
                }

                // Drop user
                $result = $ssh->executeMysql("DROP USER IF EXISTS '{$this->site->db_username}'@'localhost';");

                if (!$result['success']) {
                    throw new \Exception('Failed to drop user: ' . $result['output']);
                }

                // Flush privileges
                $ssh->executeMysql("FLUSH PRIVILEGES;");

                $log->markAsCompleted("Dropped database: {$this->site->db_name} and user: {$this->site->db_username}");
            } else {
                $log->markAsCompleted('No database to drop');
            }
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function deleteDnsRecord(Route53Service $route53): void
    {
        $log = ProvisionLog::create([
            'site_id' => $this->site->id,
            'step' => ProvisionLog::STEP_DELETE_DNS,
            'status' => ProvisionLog::STATUS_RUNNING,
        ]);

        try {
            if ($this->site->public_ip && $this->site->domain) {
                $result = $route53->deleteRecord($this->site->domain, $this->site->public_ip);

                if ($result) {
                    $log->markAsCompleted("Deleted DNS record for: {$this->site->domain}");
                } else {
                    $log->markAsCompleted('DNS record not found or already deleted');
                }
            } else {
                $log->markAsCompleted('No DNS record to delete');
            }
        } catch (\Exception $e) {
            // Log warning but don't fail the cleanup
            $log->markAsCompleted('DNS deletion error (non-critical): ' . $e->getMessage());
        }
    }
}
