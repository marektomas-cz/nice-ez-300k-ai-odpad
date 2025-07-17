<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientSecret extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'key',
        'encrypted_value',
        'metadata',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_value',
    ];

    /**
     * Get the client that owns this secret
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Check if secret has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    /**
     * Check if secret is active and not expired
     */
    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Check if secret is expiring soon
     */
    public function isExpiringSoon(int $days = 7): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at <= now()->addDays($days);
    }

    /**
     * Get secret type from metadata
     */
    public function getType(): string
    {
        return $this->metadata['type'] ?? 'unknown';
    }

    /**
     * Get secret description from metadata
     */
    public function getDescription(): ?string
    {
        return $this->metadata['description'] ?? null;
    }

    /**
     * Get rotation count from metadata
     */
    public function getRotationCount(): int
    {
        return $this->metadata['rotation_count'] ?? 0;
    }

    /**
     * Get last rotation date from metadata
     */
    public function getLastRotationDate(): ?string
    {
        return $this->metadata['rotated_at'] ?? null;
    }

    /**
     * Scope to active secrets
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to expired secrets
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope to secrets expiring soon
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays($days));
    }

    /**
     * Scope to secrets by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->whereJsonContains('metadata->type', $type);
    }

    /**
     * Scope to never used secrets
     */
    public function scopeNeverUsed($query)
    {
        return $query->whereNull('last_used_at');
    }

    /**
     * Scope to recently used secrets
     */
    public function scopeRecentlyUsed($query, int $days = 7)
    {
        return $query->where('last_used_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to secrets by client
     */
    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats(): array
    {
        return [
            'total_uses' => $this->metadata['usage_count'] ?? 0,
            'last_used' => $this->last_used_at,
            'created' => $this->created_at,
            'updated' => $this->updated_at,
            'days_since_last_use' => $this->last_used_at ? now()->diffInDays($this->last_used_at) : null,
            'days_since_creation' => now()->diffInDays($this->created_at),
        ];
    }

    /**
     * Update usage statistics
     */
    public function recordUsage(): void
    {
        $metadata = $this->metadata;
        $metadata['usage_count'] = ($metadata['usage_count'] ?? 0) + 1;
        $metadata['last_used_at'] = now()->toISOString();

        $this->update([
            'metadata' => $metadata,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Get audit trail
     */
    public function getAuditTrail(): array
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'last_used_at' => $this->last_used_at,
            'rotation_history' => $this->metadata['rotation_history'] ?? [],
            'access_history' => $this->metadata['access_history'] ?? [],
        ];
    }

    /**
     * Add to rotation history
     */
    public function addToRotationHistory(string $reason = null): void
    {
        $metadata = $this->metadata;
        $metadata['rotation_history'][] = [
            'rotated_at' => now()->toISOString(),
            'reason' => $reason,
        ];

        $this->update(['metadata' => $metadata]);
    }

    /**
     * Add to access history
     */
    public function addToAccessHistory(string $context = null): void
    {
        $metadata = $this->metadata;
        $metadata['access_history'][] = [
            'accessed_at' => now()->toISOString(),
            'context' => $context,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Keep only last 100 access records
        if (count($metadata['access_history']) > 100) {
            $metadata['access_history'] = array_slice($metadata['access_history'], -100);
        }

        $this->update(['metadata' => $metadata]);
    }

    /**
     * Get security score based on usage patterns
     */
    public function getSecurityScore(): int
    {
        $score = 100;

        // Deduct points for old secrets
        $daysSinceCreation = now()->diffInDays($this->created_at);
        if ($daysSinceCreation > 365) {
            $score -= 20;
        } elseif ($daysSinceCreation > 180) {
            $score -= 10;
        }

        // Deduct points for unused secrets
        if (!$this->last_used_at) {
            $score -= 15;
        } elseif ($this->last_used_at < now()->subDays(90)) {
            $score -= 10;
        }

        // Deduct points for secrets without expiration
        if (!$this->expires_at) {
            $score -= 10;
        } elseif ($this->isExpired()) {
            $score -= 30;
        }

        // Deduct points for low rotation frequency
        $rotationCount = $this->getRotationCount();
        if ($rotationCount === 0 && $daysSinceCreation > 90) {
            $score -= 15;
        }

        return max(0, $score);
    }

    /**
     * Get security recommendations
     */
    public function getSecurityRecommendations(): array
    {
        $recommendations = [];

        if (!$this->expires_at) {
            $recommendations[] = 'Set an expiration date for this secret';
        } elseif ($this->isExpired()) {
            $recommendations[] = 'Secret has expired and should be rotated';
        } elseif ($this->isExpiringSoon()) {
            $recommendations[] = 'Secret is expiring soon and should be rotated';
        }

        if (!$this->last_used_at) {
            $recommendations[] = 'Secret has never been used - consider removing if not needed';
        } elseif ($this->last_used_at < now()->subDays(90)) {
            $recommendations[] = 'Secret has not been used recently - consider removing if not needed';
        }

        if ($this->getRotationCount() === 0 && now()->diffInDays($this->created_at) > 90) {
            $recommendations[] = 'Consider rotating this secret regularly for better security';
        }

        $securityScore = $this->getSecurityScore();
        if ($securityScore < 70) {
            $recommendations[] = 'Security score is low - review and improve secret management';
        }

        return $recommendations;
    }
}