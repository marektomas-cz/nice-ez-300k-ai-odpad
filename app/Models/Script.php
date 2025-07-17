<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Script extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'code',
        'language',
        'is_active',
        'client_id',
        'created_by',
        'updated_by',
        'configuration',
        'version',
        'tags',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'configuration' => 'array',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'language' => 'javascript',
        'is_active' => true,
        'version' => '1.0.0',
        'configuration' => '{}',
        'tags' => '[]',
    ];

    /**
     * Get the client that owns the script
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created the script
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the script
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all execution logs for this script
     */
    public function executionLogs(): HasMany
    {
        return $this->hasMany(ScriptExecutionLog::class);
    }

    /**
     * Get recent execution logs
     */
    public function recentExecutions(int $limit = 10): HasMany
    {
        return $this->executionLogs()
            ->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    /**
     * Get successful executions count
     */
    public function getSuccessfulExecutionsAttribute(): int
    {
        return $this->executionLogs()
            ->where('status', 'success')
            ->count();
    }

    /**
     * Get failed executions count
     */
    public function getFailedExecutionsAttribute(): int
    {
        return $this->executionLogs()
            ->where('status', 'failed')
            ->count();
    }

    /**
     * Get average execution time
     */
    public function getAverageExecutionTimeAttribute(): float
    {
        return $this->executionLogs()
            ->where('status', 'success')
            ->avg('execution_time') ?? 0;
    }

    /**
     * Scope for active scripts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for scripts by client
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope for scripts by language
     */
    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    /**
     * Check if script can be executed
     */
    public function canExecute(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    /**
     * Get script configuration value
     */
    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->configuration, $key, $default);
    }

    /**
     * Set script configuration value
     */
    public function setConfigValue(string $key, $value): void
    {
        $config = $this->configuration ?? [];
        data_set($config, $key, $value);
        $this->configuration = $config;
    }

    /**
     * Get activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'is_active', 'code', 'configuration'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Validation rules for script creation/update
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'code' => 'required|string|max:65535',
            'language' => 'required|string|in:javascript',
            'is_active' => 'boolean',
            'client_id' => 'required|exists:clients,id',
            'configuration' => 'array',
            'tags' => 'array',
            'tags.*' => 'string|max:50',
        ];
    }
}