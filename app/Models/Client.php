<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'settings',
        'api_quota',
        'rate_limit',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'api_quota' => 'integer',
        'rate_limit' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'settings' => '{}',
        'api_quota' => 1000,
        'rate_limit' => 100,
    ];

    /**
     * Get all scripts for this client
     */
    public function scripts(): HasMany
    {
        return $this->hasMany(Script::class);
    }

    /**
     * Get active scripts for this client
     */
    public function activeScripts(): HasMany
    {
        return $this->scripts()->where('is_active', true);
    }

    /**
     * Get execution logs for this client
     */
    public function executionLogs(): HasMany
    {
        return $this->hasMany(ScriptExecutionLog::class);
    }

    /**
     * Get users associated with this client
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope for active clients
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get client setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set client setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    /**
     * Get script execution statistics
     */
    public function getExecutionStats(): array
    {
        $logs = $this->executionLogs();
        
        return [
            'total_executions' => $logs->count(),
            'successful_executions' => $logs->where('status', 'success')->count(),
            'failed_executions' => $logs->where('status', 'failed')->count(),
            'average_execution_time' => $logs->where('status', 'success')->avg('execution_time') ?? 0,
            'total_scripts' => $this->scripts()->count(),
            'active_scripts' => $this->activeScripts()->count(),
        ];
    }

    /**
     * Check if client has exceeded rate limit
     */
    public function hasExceededRateLimit(): bool
    {
        $recentExecutions = $this->executionLogs()
            ->where('created_at', '>=', now()->subMinute())
            ->count();
            
        return $recentExecutions >= $this->rate_limit;
    }

    /**
     * Check if client has exceeded API quota
     */
    public function hasExceededApiQuota(): bool
    {
        $monthlyExecutions = $this->executionLogs()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
            
        return $monthlyExecutions >= $this->api_quota;
    }

    /**
     * Get route key name for model binding
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}