<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'step',
        'status',
        'output',
        'error',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Step constants (matches job names)
     */
    const STEP_VALIDATE_DOMAIN = 'validate_domain';
    const STEP_PREPARE_FILESYSTEM = 'prepare_filesystem';
    const STEP_CREATE_DATABASE = 'create_database';
    const STEP_INSTALL_WORDPRESS = 'install_wordpress';
    const STEP_CONFIGURE_NGINX = 'configure_nginx';
    const STEP_RELOAD_NGINX = 'reload_nginx';
    const STEP_UPDATE_DNS = 'update_dns';
    const STEP_VERIFY_SITE = 'verify_site';

    /**
     * Cleanup/destroy steps
     */
    const STEP_REMOVE_NGINX = 'remove_nginx';
    const STEP_DELETE_FILES = 'delete_files';
    const STEP_DROP_DATABASE = 'drop_database';
    const STEP_DELETE_DNS = 'delete_dns';

    /**
     * Get the site this log belongs to
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Scope to filter by step
     */
    public function scopeForStep($query, string $step)
    {
        return $query->where('step', $step);
    }

    /**
     * Scope for completed logs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed logs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Mark as running
     */
    public function markAsRunning(): void
    {
        $this->update(['status' => self::STATUS_RUNNING]);
    }

    /**
     * Mark as completed with output
     */
    public function markAsCompleted(string $output = ''): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'output' => $output,
        ]);
    }

    /**
     * Mark as failed with error message
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error' => $error,
        ]);
    }

    /**
     * Get display name for step
     */
    public function getStepDisplayNameAttribute(): string
    {
        return match ($this->step) {
            self::STEP_VALIDATE_DOMAIN => 'Validate Domain',
            self::STEP_PREPARE_FILESYSTEM => 'Prepare Filesystem',
            self::STEP_CREATE_DATABASE => 'Create Database',
            self::STEP_INSTALL_WORDPRESS => 'Install WordPress',
            self::STEP_CONFIGURE_NGINX => 'Configure Nginx',
            self::STEP_RELOAD_NGINX => 'Reload Nginx',
            self::STEP_UPDATE_DNS => 'Update DNS',
            self::STEP_VERIFY_SITE => 'Verify Site',
            self::STEP_REMOVE_NGINX => 'Remove Nginx Config',
            self::STEP_DELETE_FILES => 'Delete Files',
            self::STEP_DROP_DATABASE => 'Drop Database',
            self::STEP_DELETE_DNS => 'Delete DNS Record',
            default => ucwords(str_replace('_', ' ', $this->step)),
        };
    }
}
