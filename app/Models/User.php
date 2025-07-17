<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'client_id',
        'is_active',
        'last_login_at',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'preferences' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the client that owns the user
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the scripts created by this user
     */
    public function createdScripts()
    {
        return $this->hasMany(Script::class, 'created_by');
    }

    /**
     * Get the scripts updated by this user
     */
    public function updatedScripts()
    {
        return $this->hasMany(Script::class, 'updated_by');
    }

    /**
     * Get the execution logs for this user
     */
    public function executionLogs()
    {
        return $this->hasMany(ScriptExecutionLog::class, 'executed_by');
    }

    /**
     * Check if user can manage scripts
     */
    public function canManageScripts(): bool
    {
        return $this->hasPermissionTo('manage scripts') || $this->hasRole('admin');
    }

    /**
     * Check if user can execute scripts
     */
    public function canExecuteScripts(): bool
    {
        return $this->hasPermissionTo('execute scripts') || $this->hasRole(['admin', 'script-executor']);
    }

    /**
     * Check if user can view monitoring data
     */
    public function canViewMonitoring(): bool
    {
        return $this->hasPermissionTo('view monitoring') || $this->hasRole(['admin', 'monitoring-viewer']);
    }

    /**
     * Check if user can manage client settings
     */
    public function canManageClient(): bool
    {
        return $this->hasPermissionTo('manage client') || $this->hasRole('admin');
    }

    /**
     * Check if user belongs to the same client as the given model
     */
    public function isSameClient($model): bool
    {
        if (!$model || !isset($model->client_id)) {
            return false;
        }

        return $this->client_id === $model->client_id;
    }

    /**
     * Scope to active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to users by client
     */
    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Get user's role names
     */
    public function getRoleNamesAttribute(): array
    {
        return $this->roles->pluck('name')->toArray();
    }

    /**
     * Get user's permission names
     */
    public function getPermissionNamesAttribute(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Check if user has elevated privileges
     */
    public function hasElevatedPrivileges(): bool
    {
        return $this->hasRole(['admin', 'super-admin']);
    }

    /**
     * Get effective permissions including role permissions
     */
    public function getEffectivePermissions(): array
    {
        $directPermissions = $this->permissions->pluck('name')->toArray();
        $rolePermissions = $this->roles->flatMap->permissions->pluck('name')->toArray();
        
        return array_unique(array_merge($directPermissions, $rolePermissions));
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get user's display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->email;
    }

    /**
     * Check if user is within their client's rate limits
     */
    public function isWithinRateLimit(): bool
    {
        if (!$this->client) {
            return false;
        }

        $recentExecutions = $this->executionLogs()
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        return $recentExecutions < $this->client->rate_limit;
    }

    /**
     * Check if user is within their client's monthly quota
     */
    public function isWithinMonthlyQuota(): bool
    {
        if (!$this->client) {
            return false;
        }

        $monthlyExecutions = $this->executionLogs()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return $monthlyExecutions < $this->client->api_quota;
    }
}