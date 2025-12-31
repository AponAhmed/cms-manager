<?php

namespace App\Services\Aws;

use App\Models\Site;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class SshService
{
    private ?SSH2 $connection = null;
    private string $host;
    private string $user;
    private string $privateKey;

    /**
     * Create SSH service for a specific site
     */
    public function __construct(Site $site)
    {
        if (!$site->public_ip) {
            throw new \Exception("Site does not have a public IP address");
        }

        if (!$site->private_key) {
            throw new \Exception("Site does not have an SSH private key");
        }

        $this->host = $site->public_ip;
        $this->user = config('aws.ssh.user', 'ec2-user');
        $this->privateKey = $site->private_key;
    }

    /**
     * Connect to the EC2 instance
     */
    public function connect(): void
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            return;
        }

        $timeout = config('aws.ssh.connection_timeout', 30);
        $this->connection = new SSH2($this->host, 22, $timeout);

        // Load private key from string
        $key = PublicKeyLoader::load($this->privateKey);

        if (!$this->connection->login($this->user, $key)) {
            throw new \Exception("SSH login failed to {$this->host}");
        }
    }

    /**
     * Execute a command on the remote server
     */
    public function execute(string $command): array
    {
        $this->connect();

        $output = $this->connection->exec($command);
        $exitCode = $this->connection->getExitStatus();

        return [
            'output' => $output,
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
        ];
    }

    /**
     * Execute multiple commands
     */
    public function executeMultiple(array $commands): array
    {
        $results = [];
        
        foreach ($commands as $command) {
            $result = $this->execute($command);
            $results[] = $result;
            
            // Stop on first failure
            if (!$result['success']) {
                break;
            }
        }

        return $results;
    }

    /**
     * Execute MySQL command
     */
    public function executeMysql(string $sql, string $rootPassword): array
    {
        $command = sprintf(
            'mysql -u root -p%s -e %s',
            escapeshellarg($rootPassword),
            escapeshellarg($sql)
        );

        return $this->execute($command);
    }

    /**
     * Upload content as a file
     */
    public function uploadContent(string $content, string $remotePath): bool
    {
        $this->connect();
        
        return $this->connection->put($remotePath, $content);
    }

    /**
     * Check if a file exists on remote server
     */
    public function fileExists(string $remotePath): bool
    {
        $result = $this->execute("test -f {$remotePath} && echo 'exists' || echo 'not found'");
        
        return str_contains($result['output'], 'exists');
    }

    /**
     * Check if a directory exists on remote server
     */
    public function directoryExists(string $remotePath): bool
    {
        $result = $this->execute("test -d {$remotePath} && echo 'exists' || echo 'not found'");
        
        return str_contains($result['output'], 'exists');
    }

    /**
     * Create a directory on remote server
     */
    public function createDirectory(string $path, string $owner = 'nginx:nginx', string $permissions = '755'): array
    {
        $commands = [
            "sudo mkdir -p {$path}",
            "sudo chown {$owner} {$path}",
            "sudo chmod {$permissions} {$path}",
        ];

        return $this->executeMultiple($commands);
    }

    /**
     * Delete a directory on remote server
     */
    public function deleteDirectory(string $path): array
    {
        return $this->execute("sudo rm -rf {$path}");
    }

    /**
     * Delete a file on remote server
     */
    public function deleteFile(string $path): array
    {
        return $this->execute("sudo rm -f {$path}");
    }

    /**
     * Test Nginx configuration
     */
    public function testNginxConfig(): array
    {
        return $this->execute('sudo nginx -t');
    }

    /**
     * Reload Nginx
     */
    public function reloadNginx(): array
    {
        return $this->execute('sudo systemctl reload nginx');
    }

    /**
     * Restart Nginx
     */
    public function restartNginx(): array
    {
        return $this->execute('sudo systemctl restart nginx');
    }

    /**
     * Get Nginx status
     */
    public function getNginxStatus(): array
    {
        return $this->execute('sudo systemctl status nginx');
    }

    /**
     * Disconnect from the server
     */
    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    /**
     * Destructor to ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
