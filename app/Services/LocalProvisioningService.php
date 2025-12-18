<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LocalProvisioningService
{
    /**
     * Execute a shell command locally
     */
    public function execute(string $command): array
    {
        Log::info('Executing local command: ' . $command);
        
        exec($command . ' 2>&1', $output, $exitCode);
        
        $outputString = implode("\n", $output);
        
        return [
            'output' => $outputString,
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
        ];
    }

    /**
     * Execute a command with sudo privileges
     */
    public function executeSudo(string $command): array
    {
        return $this->execute('sudo ' . $command);
    }

    /**
     * Execute MySQL command
     */
    public function executeMysql(string $sql): array
    {
        $host = config('provisioning.local.mysql.host');
        $user = config('provisioning.local.mysql.root_user');
        $password = config('provisioning.local.mysql.root_password');

        $command = sprintf(
            'mysql -h %s -u %s -p%s -e %s',
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($password),
            escapeshellarg($sql)
        );

        $result = $this->execute($command);

        // Fallback to sudo mysql (socket auth) if access denied or connection failed
        if (!$result['success'] && (str_contains($result['output'], 'Access denied') || str_contains($result['output'], 'Can\'t connect'))) {
            Log::info('MySQL access denied. Attempting fallback to sudo mysql (socket auth)...');
            
            // Try with sudo, no host (force socket), no password (socket auth)
            $sudoCommand = sprintf(
                'sudo mysql -e %s',
                escapeshellarg($sql)
            );
            
            return $this->execute($sudoCommand);
        }

        return $result;
    }

    /**
     * Execute multiple commands in sequence
     */
    public function executeMultiple(array $commands): array
    {
        $results = [];
        
        foreach ($commands as $command) {
            $result = $this->execute($command);
            $results[] = $result;
            
            if (!$result['success']) {
                break;
            }
        }

        return $results;
    }

    /**
     * Create a directory with proper permissions
     */
    public function createDirectory(string $path, string $owner = 'www-data:www-data', string $permissions = '755'): array
    {
        $commands = [
            "mkdir -p {$path}",
            "chown {$owner} {$path}",
            "chmod {$permissions} {$path}",
        ];

        return $this->executeMultiple($commands);
    }

    /**
     * Delete a directory
     */
    public function deleteDirectory(string $path): array
    {
        return $this->executeSudo("rm -rf {$path}");
    }

    /**
     * Delete a file
     */
    public function deleteFile(string $path): array
    {
        return $this->executeSudo("rm -f {$path}");
    }

    /**
     * Test Nginx configuration
     */
    public function testNginxConfig(): array
    {
        return $this->executeSudo('nginx -t');
    }

    /**
     * Reload Nginx
     */
    public function reloadNginx(): array
    {
        return $this->executeSudo('systemctl reload nginx');
    }

    /**
     * Add domain to /etc/hosts
     */
    public function addToHosts(string $domain): bool
    {
        // Check if already exists
        $checkCommand = sprintf('grep -F -q %s /etc/hosts', escapeshellarg($domain));
        $check = $this->execute($checkCommand);
        
        if ($check['success']) {
            Log::info("Domain {$domain} already in /etc/hosts");
            return true;
        }

        // Use temp file strategy to avoid prompts with sudo tee
        $tempFile = tempnam(sys_get_temp_dir(), 'hosts_');
        
        // Copy current hosts
        if (!copy('/etc/hosts', $tempFile)) {
            Log::error("Failed to copy /etc/hosts to temp file");
            return false;
        }

        // Append new entry
        $hostsEntry = "\n127.0.0.1\t{$domain}";
        file_put_contents($tempFile, $hostsEntry, FILE_APPEND);

        // Move into place with sudo using allowed commands
        $commands = [
            "chown root:root {$tempFile}",
            "chmod 644 {$tempFile}",
            "mv {$tempFile} /etc/hosts"
        ];
        
        foreach ($commands as $cmd) {
            $result = $this->executeSudo($cmd);
            if (!$result['success']) {
                Log::error("Failed to update hosts file: " . $result['output']);
                return false;
            }
        }
        
        Log::info("Added {$domain} to /etc/hosts");
        
        return true;
    }

    /**
     * Remove domain from /etc/hosts
     */
    public function removeFromHosts(string $domain): bool
    {
        // Validate domain format for safety since we can't use escapeshellarg with sed regex
        if (!preg_match('/^[a-z0-9.-]+$/i', $domain)) {
            Log::error("Invalid domain for hosts removal: $domain");
            return false;
        }

        // Escape dots for regex
        $safeDomain = str_replace('.', '\.', $domain);

        $command = sprintf(
            "sudo sed -i '/%s/d' /etc/hosts",
            $safeDomain
        );
        
        $result = $this->execute($command);
        
        if ($result['success']) {
            Log::info("Removed {$domain} from /etc/hosts");
        }
        
        return $result['success'];
    }

    /**
     * Write content to a file
     */
    public function writeFile(string $path, string $content): bool
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'wp_');
        file_put_contents($tempFile, $content);
        
        $result = $this->executeSudo("mv {$tempFile} {$path}");
        
        return $result['success'];
    }
}
