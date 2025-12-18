<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Configuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
        'description',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Configuration keys
     */
    const KEY_EC2_PUBLIC_IP = 'ec2_public_ip';
    const KEY_EC2_SSH_KEY_PATH = 'ec2_ssh_key_path';
    const KEY_EC2_SSH_USER = 'ec2_ssh_user';
    const KEY_MYSQL_ROOT_PASSWORD = 'mysql_root_password';
    const KEY_AWS_ROUTE53_HOSTED_ZONE_ID = 'aws_route53_hosted_zone_id';

    /**
     * Get a configuration value
     */
    public static function get(string $key, $default = null)
    {
        $config = self::where('key', $key)->first();

        if (!$config) {
            return $default;
        }

        if ($config->is_encrypted) {
            try {
                return Crypt::decryptString($config->value);
            } catch (\Exception $e) {
                return $default;
            }
        }

        return $config->value;
    }

    /**
     * Set a configuration value
     */
    public static function set(string $key, $value, bool $encrypted = false, ?string $description = null): self
    {
        $storedValue = $encrypted ? Crypt::encryptString($value) : $value;

        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'is_encrypted' => $encrypted,
                'description' => $description,
            ]
        );
    }

    /**
     * Check if a configuration exists
     */
    public static function has(string $key): bool
    {
        return self::where('key', $key)->exists();
    }

    /**
     * Delete a configuration
     */
    public static function remove(string $key): bool
    {
        return self::where('key', $key)->delete();
    }

    /**
     * Get all configurations as key-value array
     */
    public static function getAll(bool $includeEncrypted = false): array
    {
        $configs = self::all();
        $result = [];

        foreach ($configs as $config) {
            if ($config->is_encrypted && !$includeEncrypted) {
                $result[$config->key] = '***ENCRYPTED***';
            } else {
                $result[$config->key] = $config->is_encrypted
                    ? Crypt::decryptString($config->value)
                    : $config->value;
            }
        }

        return $result;
    }
}
