<?php

namespace App\Services;

use App\Models\Configuration;
use Aws\Route53\Route53Client;
use Aws\Exception\AwsException;

class Route53Service
{
    private Route53Client $client;
    private string $hostedZoneId;

    public function __construct()
    {
        $this->client = new Route53Client([
            'version' => 'latest',
            'region' => config('aws.default_region', 'us-east-1'),
            'credentials' => [
                'key' => config('aws.access_key_id'),
                'secret' => config('aws.secret_access_key'),
            ],
        ]);

        $this->hostedZoneId = Configuration::get(
            Configuration::KEY_AWS_ROUTE53_HOSTED_ZONE_ID,
            config('aws.route53_hosted_zone_id')
        );
    }

    /**
     * Create an A record for a domain
     */
    public function createARecord(string $domain, string $ipAddress, int $ttl = 300): ?string
    {
        try {
            $result = $this->client->changeResourceRecordSets([
                'HostedZoneId' => $this->hostedZoneId,
                'ChangeBatch' => [
                    'Comment' => "Creating A record for {$domain}",
                    'Changes' => [
                        [
                            'Action' => 'CREATE',
                            'ResourceRecordSet' => [
                                'Name' => $domain,
                                'Type' => 'A',
                                'TTL' => $ttl,
                                'ResourceRecords' => [
                                    ['Value' => $ipAddress],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            return $result['ChangeInfo']['Id'] ?? null;
        } catch (AwsException $e) {
            throw new \Exception("Failed to create DNS record: " . $e->getMessage());
        }
    }

    /**
     * Delete a DNS record
     */
    public function deleteRecord(string $domain, string $ipAddress, string $type = 'A'): bool
    {
        try {
            $this->client->changeResourceRecordSets([
                'HostedZoneId' => $this->hostedZoneId,
                'ChangeBatch' => [
                    'Comment' => "Deleting {$type} record for {$domain}",
                    'Changes' => [
                        [
                            'Action' => 'DELETE',
                            'ResourceRecordSet' => [
                                'Name' => $domain,
                                'Type' => $type,
                                'TTL' => 300,
                                'ResourceRecords' => [
                                    ['Value' => $ipAddress],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            return true;
        } catch (AwsException $e) {
            // If record doesn't exist, consider it a success
            if (str_contains($e->getMessage(), 'not found')) {
                return true;
            }
            throw new \Exception("Failed to delete DNS record: " . $e->getMessage());
        }
    }

    /**
     * Check if a record exists
     */
    public function recordExists(string $domain, string $type = 'A'): bool
    {
        try {
            $result = $this->client->listResourceRecordSets([
                'HostedZoneId' => $this->hostedZoneId,
                'StartRecordName' => $domain,
                'StartRecordType' => $type,
                'MaxItems' => '1',
            ]);

            foreach ($result['ResourceRecordSets'] as $record) {
                if ($record['Name'] === $domain . '.' && $record['Type'] === $type) {
                    return true;
                }
            }

            return false;
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Get record details
     */
    public function getRecord(string $domain, string $type = 'A'): ?array
    {
        try {
            $result = $this->client->listResourceRecordSets([
                'HostedZoneId' => $this->hostedZoneId,
                'StartRecordName' => $domain,
                'StartRecordType' => $type,
                'MaxItems' => '1',
            ]);

            foreach ($result['ResourceRecordSets'] as $record) {
                if ($record['Name'] === $domain . '.' && $record['Type'] === $type) {
                    return $record;
                }
            }

            return null;
        } catch (AwsException $e) {
            return null;
        }
    }

    /**
     * Wait for DNS change to propagate
     */
    public function waitForChange(string $changeId, int $maxAttempts = 30): bool
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                $result = $this->client->getChange([
                    'Id' => $changeId,
                ]);

                if ($result['ChangeInfo']['Status'] === 'INSYNC') {
                    return true;
                }

                sleep(10); // Wait 10 seconds between checks
                $attempts++;
            } catch (AwsException $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * List all records in the hosted zone
     */
    public function listRecords(): array
    {
        try {
            $result = $this->client->listResourceRecordSets([
                'HostedZoneId' => $this->hostedZoneId,
            ]);

            return $result['ResourceRecordSets'] ?? [];
        } catch (AwsException $e) {
            throw new \Exception("Failed to list DNS records: " . $e->getMessage());
        }
    }

    /**
     * Get hosted zone details
     */
    public function getHostedZone(): ?array
    {
        try {
            $result = $this->client->getHostedZone([
                'Id' => $this->hostedZoneId,
            ]);

            return $result['HostedZone'] ?? null;
        } catch (AwsException $e) {
            return null;
        }
    }

    /**
     * Validate domain format
     */
    public static function isValidDomain(string $domain): bool
    {
        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i',
            $domain
        );
    }
}
