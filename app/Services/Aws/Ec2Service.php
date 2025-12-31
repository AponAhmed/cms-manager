<?php

namespace App\Services\Aws;

use App\Models\Site;
use Aws\Ec2\Ec2Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Str;

class Ec2Service
{
    private Ec2Client $client;

    public function __construct()
    {
        $this->client = new Ec2Client([
            'version' => 'latest',
            'region' => config('aws.default_region', 'us-east-1'),
            'credentials' => [
                'key' => config('aws.access_key_id'),
                'secret' => config('aws.secret_access_key'),
            ],
        ]);
    }

    /**
     * Launch a new EC2 instance for a WordPress site
     */
    public function launchInstance(Site $site): array
    {
        // Generate MySQL root password
        $mysqlRootPassword = Str::random(config('aws.security.mysql_root_password_length', 24));

        // Create key pair
        $keyPair = $this->createKeyPair($site);

        // Get or create security group
        $securityGroupId = $this->getOrCreateSecurityGroup();

        // Generate user data script
        $userData = $this->generateUserDataScript($mysqlRootPassword);

        try {
            $result = $this->client->runInstances([
                'ImageId' => config('aws.ec2.ami_id'),
                'InstanceType' => config('aws.ec2.instance_type', 't2.micro'),
                'MinCount' => 1,
                'MaxCount' => 1,
                'KeyName' => $keyPair['KeyName'],
                'SecurityGroupIds' => [$securityGroupId],
                'UserData' => base64_encode($userData),
                'TagSpecifications' => [
                    [
                        'ResourceType' => 'instance',
                        'Tags' => [
                            ['Key' => 'Name', 'Value' => "WordPress-{$site->domain}"],
                            ['Key' => 'ManagedBy', 'Value' => 'cms-manager'],
                            ['Key' => 'SiteId', 'Value' => (string) $site->id],
                            ['Key' => 'Domain', 'Value' => $site->domain],
                        ],
                    ],
                ],
            ]);

            $instanceId = $result['Instances'][0]['InstanceId'];

            return [
                'instance_id' => $instanceId,
                'key_pair_name' => $keyPair['KeyName'],
                'private_key' => $keyPair['KeyMaterial'],
                'security_group_id' => $securityGroupId,
                'mysql_root_password' => $mysqlRootPassword,
            ];
        } catch (AwsException $e) {
            // Cleanup key pair if instance launch fails
            $this->deleteKeyPair($keyPair['KeyName']);
            throw new \Exception("Failed to launch EC2 instance: " . $e->getMessage());
        }
    }

    /**
     * Wait for instance to be running and get public IP
     */
    public function waitForInstanceReady(string $instanceId, int $maxRetries = null): array
    {
        $maxRetries = $maxRetries ?? config('aws.ssh.max_retries', 10);
        $retryDelay = config('aws.ssh.retry_delay', 30);

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $result = $this->client->describeInstances([
                    'InstanceIds' => [$instanceId],
                ]);

                $instance = $result['Reservations'][0]['Instances'][0] ?? null;

                if (!$instance) {
                    throw new \Exception("Instance not found: {$instanceId}");
                }

                $state = $instance['State']['Name'];

                if ($state === 'running' && !empty($instance['PublicIpAddress'])) {
                    return [
                        'instance_id' => $instanceId,
                        'public_ip' => $instance['PublicIpAddress'],
                        'public_dns' => $instance['PublicDnsName'] ?? null,
                        'state' => $state,
                    ];
                }

                if ($state === 'terminated' || $state === 'shutting-down') {
                    throw new \Exception("Instance is {$state}: {$instanceId}");
                }

                sleep($retryDelay);
            } catch (AwsException $e) {
                if ($i === $maxRetries - 1) {
                    throw new \Exception("Failed to get instance status: " . $e->getMessage());
                }
                sleep($retryDelay);
            }
        }

        throw new \Exception("Instance did not become ready within timeout: {$instanceId}");
    }

    /**
     * Wait for SSH to be available on the instance
     */
    public function waitForSshReady(string $publicIp, int $maxRetries = null): bool
    {
        $maxRetries = $maxRetries ?? config('aws.ssh.max_retries', 10);
        $retryDelay = config('aws.ssh.retry_delay', 30);
        $timeout = config('aws.ssh.connection_timeout', 30);

        for ($i = 0; $i < $maxRetries; $i++) {
            $connection = @fsockopen($publicIp, 22, $errno, $errstr, $timeout);
            
            if ($connection) {
                fclose($connection);
                // Wait a bit more for SSH to fully initialize
                sleep(10);
                return true;
            }

            sleep($retryDelay);
        }

        return false;
    }

    /**
     * Terminate an EC2 instance
     */
    public function terminateInstance(string $instanceId): bool
    {
        try {
            $this->client->terminateInstances([
                'InstanceIds' => [$instanceId],
            ]);

            return true;
        } catch (AwsException $e) {
            throw new \Exception("Failed to terminate instance: " . $e->getMessage());
        }
    }

    /**
     * Get instance details
     */
    public function getInstanceDetails(string $instanceId): ?array
    {
        try {
            $result = $this->client->describeInstances([
                'InstanceIds' => [$instanceId],
            ]);

            return $result['Reservations'][0]['Instances'][0] ?? null;
        } catch (AwsException $e) {
            return null;
        }
    }

    /**
     * Create a key pair for the site
     */
    public function createKeyPair(Site $site): array
    {
        $keyName = config('aws.ec2.key_name_prefix') . $site->id . '-' . Str::random(8);

        try {
            $result = $this->client->createKeyPair([
                'KeyName' => $keyName,
                'TagSpecifications' => [
                    [
                        'ResourceType' => 'key-pair',
                        'Tags' => [
                            ['Key' => 'ManagedBy', 'Value' => 'cms-manager'],
                            ['Key' => 'SiteId', 'Value' => (string) $site->id],
                        ],
                    ],
                ],
            ]);

            return [
                'KeyName' => $result['KeyName'],
                'KeyMaterial' => $result['KeyMaterial'],
            ];
        } catch (AwsException $e) {
            throw new \Exception("Failed to create key pair: " . $e->getMessage());
        }
    }

    /**
     * Delete a key pair
     */
    public function deleteKeyPair(string $keyName): bool
    {
        try {
            $this->client->deleteKeyPair([
                'KeyName' => $keyName,
            ]);

            return true;
        } catch (AwsException $e) {
            // Ignore if key pair doesn't exist
            return true;
        }
    }

    /**
     * Get or create the security group for WordPress sites
     */
    public function getOrCreateSecurityGroup(): string
    {
        $groupName = config('aws.ec2.security_group_name', 'cms-manager-wordpress');

        try {
            // Check if security group exists
            $result = $this->client->describeSecurityGroups([
                'GroupNames' => [$groupName],
            ]);

            return $result['SecurityGroups'][0]['GroupId'];
        } catch (AwsException $e) {
            // Security group doesn't exist, create it
            return $this->createSecurityGroup($groupName);
        }
    }

    /**
     * Create a security group with WordPress ports
     */
    private function createSecurityGroup(string $groupName): string
    {
        try {
            // Create the security group
            $result = $this->client->createSecurityGroup([
                'GroupName' => $groupName,
                'Description' => 'Security group for CMS Manager WordPress sites',
                'TagSpecifications' => [
                    [
                        'ResourceType' => 'security-group',
                        'Tags' => [
                            ['Key' => 'ManagedBy', 'Value' => 'cms-manager'],
                        ],
                    ],
                ],
            ]);

            $groupId = $result['GroupId'];

            // Add inbound rules for SSH (22), HTTP (80), HTTPS (443)
            $this->client->authorizeSecurityGroupIngress([
                'GroupId' => $groupId,
                'IpPermissions' => [
                    [
                        'IpProtocol' => 'tcp',
                        'FromPort' => 22,
                        'ToPort' => 22,
                        'IpRanges' => [['CidrIp' => '0.0.0.0/0', 'Description' => 'SSH access']],
                    ],
                    [
                        'IpProtocol' => 'tcp',
                        'FromPort' => 80,
                        'ToPort' => 80,
                        'IpRanges' => [['CidrIp' => '0.0.0.0/0', 'Description' => 'HTTP access']],
                    ],
                    [
                        'IpProtocol' => 'tcp',
                        'FromPort' => 443,
                        'ToPort' => 443,
                        'IpRanges' => [['CidrIp' => '0.0.0.0/0', 'Description' => 'HTTPS access']],
                    ],
                ],
            ]);

            return $groupId;
        } catch (AwsException $e) {
            throw new \Exception("Failed to create security group: " . $e->getMessage());
        }
    }

    /**
     * Generate user data script for WordPress server setup
     * Compatible with Amazon Linux 2023
     */
    private function generateUserDataScript(string $mysqlRootPassword): string
    {
        return <<<BASH
#!/bin/bash
set -e

# Log all output
exec > /var/log/user-data.log 2>&1

echo "Starting WordPress server setup on Amazon Linux 2023..."

# Update system
dnf update -y

# Install Nginx
dnf install -y nginx
systemctl enable nginx
systemctl start nginx

# Install PHP 8.1 and extensions
dnf install -y php php-fpm php-mysqlnd php-json php-gd php-mbstring php-xml php-zip php-curl

# Configure PHP-FPM for Nginx
sed -i 's/user = apache/user = nginx/' /etc/php-fpm.d/www.conf
sed -i 's/group = apache/group = nginx/' /etc/php-fpm.d/www.conf
sed -i 's/listen = \/run\/php-fpm\/www.sock/listen = \/var\/run\/php-fpm\/www.sock/' /etc/php-fpm.d/www.conf
sed -i 's/;listen.owner = nobody/listen.owner = nginx/' /etc/php-fpm.d/www.conf
sed -i 's/;listen.group = nobody/listen.group = nginx/' /etc/php-fpm.d/www.conf
sed -i 's/;listen.mode = 0660/listen.mode = 0660/' /etc/php-fpm.d/www.conf

# Create PHP-FPM socket directory
mkdir -p /var/run/php-fpm
chown nginx:nginx /var/run/php-fpm

systemctl enable php-fpm
systemctl start php-fpm

# Install MariaDB
dnf install -y mariadb105-server
systemctl enable mariadb
systemctl start mariadb

# Set MySQL root password
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '{$mysqlRootPassword}';"
mysql -u root -p'{$mysqlRootPassword}' -e "DELETE FROM mysql.user WHERE User='';"
mysql -u root -p'{$mysqlRootPassword}' -e "DROP DATABASE IF EXISTS test;"
mysql -u root -p'{$mysqlRootPassword}' -e "FLUSH PRIVILEGES;"

# Install WP-CLI
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp

# Create web directory
mkdir -p /var/www
chown -R nginx:nginx /var/www

# Create Nginx sites directories
mkdir -p /etc/nginx/sites-available
mkdir -p /etc/nginx/sites-enabled

# Update Nginx main config to include sites-enabled
if ! grep -q "include /etc/nginx/sites-enabled" /etc/nginx/nginx.conf; then
    sed -i '/include \\/etc\\/nginx\\/conf.d\\/\\*.conf;/a\\    include /etc/nginx/sites-enabled/*.conf;' /etc/nginx/nginx.conf
fi

# Restart services
systemctl restart nginx
systemctl restart php-fpm

# Create flag file to indicate setup is complete
touch /var/log/wordpress-setup-complete

echo "WordPress server setup complete!"
BASH;
    }
}
