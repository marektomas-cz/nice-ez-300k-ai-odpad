<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScriptVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'script_id',
        'version_number',
        'name',
        'description',
        'code',
        'language',
        'configuration',
        'tags',
        'is_active',
        'change_notes',
        'created_by',
        'checksum',
        'size_bytes',
        'performance_metrics',
        'security_scan_results',
    ];

    protected $casts = [
        'configuration' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'performance_metrics' => 'array',
        'security_scan_results' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the script that owns this version
     */
    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    /**
     * Get the user who created this version
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the client that owns this version (through script)
     */
    public function client(): BelongsTo
    {
        return $this->script->client();
    }

    /**
     * Generate checksum for the script code
     */
    public function generateChecksum(): string
    {
        return hash('sha256', $this->code);
    }

    /**
     * Calculate size in bytes
     */
    public function calculateSize(): int
    {
        return strlen($this->code);
    }

    /**
     * Check if this version is the current active version
     */
    public function isCurrentVersion(): bool
    {
        return $this->script->current_version_id === $this->id;
    }

    /**
     * Get the next version number for a script
     */
    public static function getNextVersionNumber(Script $script): string
    {
        $latestVersion = $script->versions()->orderBy('version_number', 'desc')->first();
        
        if (!$latestVersion) {
            return '1.0.0';
        }

        // Parse semantic version
        $parts = explode('.', $latestVersion->version_number);
        $major = (int)($parts[0] ?? 1);
        $minor = (int)($parts[1] ?? 0);
        $patch = (int)($parts[2] ?? 0);

        // Increment patch version
        $patch++;

        return "{$major}.{$minor}.{$patch}";
    }

    /**
     * Get version comparison data
     */
    public function getComparisonData(): array
    {
        return [
            'version_number' => $this->version_number,
            'created_at' => $this->created_at,
            'creator' => $this->creator->name ?? 'System',
            'change_notes' => $this->change_notes,
            'code_size' => $this->size_bytes,
            'checksum' => $this->checksum,
            'is_current' => $this->isCurrentVersion(),
            'performance_metrics' => $this->performance_metrics,
            'security_scan_results' => $this->security_scan_results,
        ];
    }

    /**
     * Get diff between this version and another
     */
    public function getDiffWith(ScriptVersion $other): array
    {
        return [
            'from_version' => $other->version_number,
            'to_version' => $this->version_number,
            'code_changes' => $this->calculateCodeDiff($other->code, $this->code),
            'metadata_changes' => $this->calculateMetadataDiff($other),
            'size_change' => $this->size_bytes - $other->size_bytes,
            'checksum_changed' => $this->checksum !== $other->checksum,
        ];
    }

    /**
     * Calculate code differences
     */
    private function calculateCodeDiff(string $oldCode, string $newCode): array
    {
        $oldLines = explode("\n", $oldCode);
        $newLines = explode("\n", $newCode);

        $changes = [];
        $oldLineCount = count($oldLines);
        $newLineCount = count($newLines);

        // Simple diff algorithm
        for ($i = 0; $i < max($oldLineCount, $newLineCount); $i++) {
            $oldLine = $oldLines[$i] ?? null;
            $newLine = $newLines[$i] ?? null;

            if ($oldLine === null && $newLine !== null) {
                $changes[] = [
                    'type' => 'added',
                    'line' => $i + 1,
                    'content' => $newLine,
                ];
            } elseif ($oldLine !== null && $newLine === null) {
                $changes[] = [
                    'type' => 'removed',
                    'line' => $i + 1,
                    'content' => $oldLine,
                ];
            } elseif ($oldLine !== $newLine) {
                $changes[] = [
                    'type' => 'modified',
                    'line' => $i + 1,
                    'old_content' => $oldLine,
                    'new_content' => $newLine,
                ];
            }
        }

        return $changes;
    }

    /**
     * Calculate metadata differences
     */
    private function calculateMetadataDiff(ScriptVersion $other): array
    {
        $changes = [];

        if ($this->name !== $other->name) {
            $changes['name'] = ['from' => $other->name, 'to' => $this->name];
        }

        if ($this->description !== $other->description) {
            $changes['description'] = ['from' => $other->description, 'to' => $this->description];
        }

        if ($this->configuration !== $other->configuration) {
            $changes['configuration'] = ['from' => $other->configuration, 'to' => $this->configuration];
        }

        if ($this->tags !== $other->tags) {
            $changes['tags'] = ['from' => $other->tags, 'to' => $this->tags];
        }

        return $changes;
    }

    /**
     * Scope to get versions by script
     */
    public function scopeForScript($query, Script $script)
    {
        return $query->where('script_id', $script->id);
    }

    /**
     * Scope to get active versions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get versions by client
     */
    public function scopeByClient($query, $clientId)
    {
        return $query->whereHas('script', function ($q) use ($clientId) {
            $q->where('client_id', $clientId);
        });
    }

    /**
     * Get version statistics
     */
    public function getStats(): array
    {
        return [
            'lines_of_code' => count(explode("\n", $this->code)),
            'characters' => strlen($this->code),
            'size_bytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'created_at' => $this->created_at,
            'age_days' => now()->diffInDays($this->created_at),
            'is_current' => $this->isCurrentVersion(),
            'has_performance_data' => !empty($this->performance_metrics),
            'has_security_scan' => !empty($this->security_scan_results),
        ];
    }

    /**
     * Create version from script
     */
    public static function createFromScript(Script $script, string $changeNotes = '', ?User $creator = null): self
    {
        $version = new self([
            'script_id' => $script->id,
            'version_number' => self::getNextVersionNumber($script),
            'name' => $script->name,
            'description' => $script->description,
            'code' => $script->code,
            'language' => $script->language,
            'configuration' => $script->configuration,
            'tags' => $script->tags,
            'is_active' => $script->is_active,
            'change_notes' => $changeNotes,
            'created_by' => $creator?->id ?? auth()->id(),
        ]);

        $version->checksum = $version->generateChecksum();
        $version->size_bytes = $version->calculateSize();
        $version->save();

        return $version;
    }

    /**
     * Restore script to this version
     */
    public function restoreToScript(): bool
    {
        return $this->script->update([
            'name' => $this->name,
            'description' => $this->description,
            'code' => $this->code,
            'language' => $this->language,
            'configuration' => $this->configuration,
            'tags' => $this->tags,
            'is_active' => $this->is_active,
            'current_version_id' => $this->id,
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Check if version has security issues
     */
    public function hasSecurityIssues(): bool
    {
        return !empty($this->security_scan_results) && 
               isset($this->security_scan_results['issues']) && 
               count($this->security_scan_results['issues']) > 0;
    }

    /**
     * Get performance score
     */
    public function getPerformanceScore(): ?float
    {
        return $this->performance_metrics['score'] ?? null;
    }

    /**
     * Get security score
     */
    public function getSecurityScore(): ?float
    {
        return $this->security_scan_results['score'] ?? null;
    }
}