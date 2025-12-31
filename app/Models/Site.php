<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'status',
        'wp_admin_username',
        'wp_admin_password',
        'wp_admin_email',
        'db_name',
        'db_username',
        'db_password',
        'ec2_path',
        'public_ip',
        'dns_record_id',
        'provisioned_at',
        'destroyed_at',
        // EC2 instance fields
        'instance_id',
        'key_pair_name',
        'private_key',
        'security_group_id',
        'mysql_root_password',
    ];

    protected $casts = [
        'wp_admin_password' => 'encrypted',
        'db_password' => 'encrypted',
        'private_key' => 'encrypted',
        'mysql_root_password' => 'encrypted',
        'provisioned_at' => 'datetime',
        'destroyed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROVISIONING = 'provisioning';
    const STATUS_LIVE = 'live';
    const STATUS_FAILED = 'failed';
    const STATUS_DESTROYED = 'destroyed';

    /**
     * Get all provision logs for this site
     */
    public function provisionLogs(): HasMany
    {
        return $this->hasMany(ProvisionLog::class);
    }

    /**
     * Scope to filter by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for live sites
     */
    public function scopeLive($query)
    {
        return $query->where('status', self::STATUS_LIVE);
    }

    /**
     * Scope for failed sites
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_PROVISIONING => 'blue',
            self::STATUS_LIVE => 'green',
            self::STATUS_FAILED => 'red',
            self::STATUS_DESTROYED => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Check if site is provisioning
     */
    public function isProvisioning(): bool
    {
        return $this->status === self::STATUS_PROVISIONING;
    }

    /**
     * Check if site is live
     */
    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /**
     * Check if site has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark site as provisioning
     */
    public function markAsProvisioning(): void
    {
        $this->update(['status' => self::STATUS_PROVISIONING]);
    }

    /**
     * Mark site as live
     */
    public function markAsLive(): void
    {
        $this->update([
            'status' => self::STATUS_LIVE,
            'provisioned_at' => now(),
        ]);
    }

    /**
     * Mark site as failed
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    /**
     * Mark site as destroyed
     */
    public function markAsDestroyed(): void
    {
        $this->update([
            'status' => self::STATUS_DESTROYED,
            'destroyed_at' => now(),
        ]);
    }
}
