<?php

namespace App\Services\Aws;

use App\Models\Configuration;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class SshService
{
    private SSH2 $connection;
    private string $host;
    private string $user;
    private string $keyPath;

    public function __construct()
    {
        $this->host = Configuration::get(Configuration::KEY_EC2_PUBLIC_IP, config('wordpress.ec2.ip'));
        $this->user = Configuration::get(Configuration::KEY_EC2_SSH_USER, config('wordpress.ec2.ssh_user'));
        $this->keyPath = Configuration::get(Configuration::KEY_EC2_SSH_KEY_PATH, config('wordpress.ec2.ssh_key'));
    }

    /**
     * Connect to the EC2 instance
     */
    public function connect(): void
    {
        if (isset($this->connection) && $this->connection->isConnected()) {
            return;
        }

        $this->connection = new SSH2($this->host);

        // Load private key
        if (!file_exists($this->keyPath)) {
            throw new \Exception("SSH key not found at: {$this->keyPath}");
        }

        $key = PublicKeyLoader::load(file_get_contents($this->keyPath));

        if (!$this->connection->login($this->user, $key)) {
            throw new \Exception('SSH login failed');
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
    public function executeMysql(string $sql): array
    {
        $rootPassword = Configuration::get(
            Configuration::KEY_MYSQL_ROOT_PASSWORD,
            config('wordpress.mysql.root_password')
        );

        $command = sprintf(
            'mysql -u root -p%s -e %s',
            escapeshellarg($rootPassword),
            escapeshellarg($sql)
        );

        return $this->execute($command);
    }

    /**
     * Upload a file to the remote server
     */
    public function uploadFile(string $localPath, string $remotePath): bool
    {
        $this->connect();

        if (!file_exists($localPath)) {
            throw new \Exception("Local file not found: {$localPath}");
        }

        $content = file_get_contents($localPath);
        
        return $this->connection->put($remotePath, $content);
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
    public function createDirectory(string $path, string $owner = 'www-data:www-data', string $permissions = '755'): array
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
        if (isset($this->connection)) {
            $this->connection->disconnect();
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
