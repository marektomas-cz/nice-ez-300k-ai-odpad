<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScriptExecutionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'script_id',
        'client_id',
        'executed_by',
        'execution_context',
        'status',
        'output',
        'error_message',
        'execution_time',
        'memory_usage',
        'trigger_type',
        'trigger_data',
        'started_at',
        'completed_at',
        'resource_usage',
        'security_flags',
    ];

    protected $casts = [
        'execution_time' => 'float',
        'memory_usage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'trigger_data' => 'array',
        'resource_usage' => 'array',
        'security_flags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'trigger_type' => 'manual',
        'trigger_data' => '{}',
        'resource_usage' => '{}',
        'security_flags' => '[]',
    ];

    /**
     * The script this log belongs to
     */
    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    /**
     * The client this execution belongs to
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The user who executed the script
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /**
     * Scope for successful executions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed executions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for executions by trigger type
     */
    public function scopeByTriggerType($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Scope for executions within time range
     */
    public function scopeWithinTimeRange($query, $startTime, $endTime)
    {
        return $query->whereBetween('started_at', [$startTime, $endTime]);
    }

    /**
     * Scope for executions by client
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Check if execution was successful
     */
    public function wasSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if execution failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if execution is still running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Get execution duration in seconds
     */
    public function getDurationAttribute(): ?float
    {
        if ($this->started_at && $this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at, true);
        }
        return null;
    }

    /**
     * Get formatted execution time
     */
    public function getFormattedExecutionTimeAttribute(): string
    {
        if ($this->execution_time === null) {
            return 'N/A';
        }
        
        if ($this->execution_time < 1) {
            return round($this->execution_time * 1000, 2) . 'ms';
        }
        
        return round($this->execution_time, 2) . 's';
    }

    /**
     * Get formatted memory usage
     */
    public function getFormattedMemoryUsageAttribute(): string
    {
        if ($this->memory_usage === null) {
            return 'N/A';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->memory_usage;
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get resource usage value
     */
    public function getResourceUsageValue(string $key, $default = null)
    {
        return data_get($this->resource_usage, $key, $default);
    }

    /**
     * Check if execution has security flags
     */
    public function hasSecurityFlags(): bool
    {
        return !empty($this->security_flags);
    }

    /**
     * Get security flag by type
     */
    public function getSecurityFlag(string $type): ?array
    {
        return collect($this->security_flags)->firstWhere('type', $type);
    }

    /**
     * Add security flag
     */
    public function addSecurityFlag(string $type, string $message, array $context = []): void
    {
        $flags = $this->security_flags ?? [];
        $flags[] = [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ];
        $this->security_flags = $flags;
    }

    /**
     * Mark execution as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark execution as completed successfully
     */
    public function markAsSuccessful(string $output = null, array $resourceUsage = []): void
    {
        $this->update([
            'status' => 'success',
            'output' => $output,
            'completed_at' => now(),
            'resource_usage' => $resourceUsage,
        ]);
    }

    /**
     * Mark execution as failed
     */
    public function markAsFailed(string $errorMessage, array $resourceUsage = []): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
            'resource_usage' => $resourceUsage,
        ]);
    }

    /**
     * Get execution summary
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'script_name' => $this->script->name,
            'status' => $this->status,
            'execution_time' => $this->formatted_execution_time,
            'memory_usage' => $this->formatted_memory_usage,
            'trigger_type' => $this->trigger_type,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'has_security_flags' => $this->hasSecurityFlags(),
        ];
    }
}