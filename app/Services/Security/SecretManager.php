<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\ClientSecret;
use App\Models\Client;

class SecretManager
{
    private const CACHE_PREFIX = 'secret_manager_';
    private const CACHE_TTL = 300; // 5 minutes
    
    /**
     * Store a secret for a client
     */
    public function storeSecret(Client $client, string $key, string $value, array $metadata = []): bool
    {
        try {
            $encryptedValue = Crypt::encrypt($value);
            
            ClientSecret::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'key' => $key,
                ],
                [
                    'encrypted_value' => $encryptedValue,
                    'metadata' => $metadata,
                    'last_used_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            
            // Clear cache
            $this->clearSecretCache($client->id, $key);
            
            Log::info('Secret stored successfully', [
                'client_id' => $client->id,
                'key' => $key,
                'metadata' => $metadata,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to store secret', [
                'client_id' => $client->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Retrieve a secret for a client
     */
    public function getSecret(Client $client, string $key): ?string
    {
        $cacheKey = $this->getCacheKey($client->id, $key);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($client, $key) {
            try {
                $clientSecret = ClientSecret::where('client_id', $client->id)
                    ->where('key', $key)
                    ->where('is_active', true)
                    ->first();
                
                if (!$clientSecret) {
                    return null;
                }
                
                // Check if secret has expired
                if ($clientSecret->expires_at && $clientSecret->expires_at < now()) {
                    Log::warning('Secret has expired', [
                        'client_id' => $client->id,
                        'key' => $key,
                        'expires_at' => $clientSecret->expires_at,
                    ]);
                    return null;
                }
                
                // Update last used timestamp
                $clientSecret->touch('last_used_at');
                
                $decryptedValue = Crypt::decrypt($clientSecret->encrypted_value);
                
                Log::info('Secret retrieved successfully', [
                    'client_id' => $client->id,
                    'key' => $key,
                ]);
                
                return $decryptedValue;
                
            } catch (\Exception $e) {
                Log::error('Failed to retrieve secret', [
                    'client_id' => $client->id,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
                
                return null;
            }
        });
    }
    
    /**
     * Delete a secret for a client
     */
    public function deleteSecret(Client $client, string $key): bool
    {
        try {
            ClientSecret::where('client_id', $client->id)
                ->where('key', $key)
                ->delete();
            
            // Clear cache
            $this->clearSecretCache($client->id, $key);
            
            Log::info('Secret deleted successfully', [
                'client_id' => $client->id,
                'key' => $key,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to delete secret', [
                'client_id' => $client->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * List all secrets for a client (without values)
     */
    public function listSecrets(Client $client): array
    {
        try {
            $secrets = ClientSecret::where('client_id', $client->id)
                ->where('is_active', true)
                ->select(['key', 'metadata', 'created_at', 'updated_at', 'last_used_at', 'expires_at'])
                ->get();
            
            return $secrets->map(function ($secret) {
                return [
                    'key' => $secret->key,
                    'metadata' => $secret->metadata,
                    'created_at' => $secret->created_at,
                    'updated_at' => $secret->updated_at,
                    'last_used_at' => $secret->last_used_at,
                    'expires_at' => $secret->expires_at,
                    'is_expired' => $secret->expires_at && $secret->expires_at < now(),
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            Log::error('Failed to list secrets', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }
    
    /**
     * Rotate a secret (generate new value)
     */
    public function rotateSecret(Client $client, string $key, ?string $newValue = null): ?string
    {
        try {
            $secret = ClientSecret::where('client_id', $client->id)
                ->where('key', $key)
                ->first();
            
            if (!$secret) {
                return null;
            }
            
            // Generate new value if not provided
            if (!$newValue) {
                $newValue = $this->generateSecretValue($secret->metadata['type'] ?? 'random');
            }
            
            // Store old value for rollback if needed
            $oldValue = $secret->encrypted_value;
            
            // Update with new value
            $secret->update([
                'encrypted_value' => Crypt::encrypt($newValue),
                'updated_at' => now(),
                'metadata' => array_merge($secret->metadata, [
                    'rotated_at' => now()->toISOString(),
                    'rotation_count' => ($secret->metadata['rotation_count'] ?? 0) + 1,
                ]),
            ]);
            
            // Clear cache
            $this->clearSecretCache($client->id, $key);
            
            Log::info('Secret rotated successfully', [
                'client_id' => $client->id,
                'key' => $key,
                'rotation_count' => $secret->metadata['rotation_count'] ?? 1,
            ]);
            
            return $newValue;
            
        } catch (\Exception $e) {
            Log::error('Failed to rotate secret', [
                'client_id' => $client->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }
    
    /**
     * Set secret expiration
     */
    public function setSecretExpiration(Client $client, string $key, \DateTime $expiresAt): bool
    {
        try {
            ClientSecret::where('client_id', $client->id)
                ->where('key', $key)
                ->update([
                    'expires_at' => $expiresAt,
                    'updated_at' => now(),
                ]);
            
            // Clear cache
            $this->clearSecretCache($client->id, $key);
            
            Log::info('Secret expiration set successfully', [
                'client_id' => $client->id,
                'key' => $key,
                'expires_at' => $expiresAt,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to set secret expiration', [
                'client_id' => $client->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Check if secret exists
     */
    public function secretExists(Client $client, string $key): bool
    {
        return ClientSecret::where('client_id', $client->id)
            ->where('key', $key)
            ->where('is_active', true)
            ->exists();
    }
    
    /**
     * Get secret metadata
     */
    public function getSecretMetadata(Client $client, string $key): ?array
    {
        $secret = ClientSecret::where('client_id', $client->id)
            ->where('key', $key)
            ->first();
        
        if (!$secret) {
            return null;
        }
        
        return [
            'key' => $secret->key,
            'metadata' => $secret->metadata,
            'created_at' => $secret->created_at,
            'updated_at' => $secret->updated_at,
            'last_used_at' => $secret->last_used_at,
            'expires_at' => $secret->expires_at,
            'is_active' => $secret->is_active,
            'is_expired' => $secret->expires_at && $secret->expires_at < now(),
        ];
    }
    
    /**
     * Bulk update secrets
     */
    public function bulkUpdateSecrets(Client $client, array $secrets): array
    {
        $results = [];
        
        foreach ($secrets as $key => $value) {
            $results[$key] = $this->storeSecret($client, $key, $value);
        }
        
        return $results;
    }
    
    /**
     * Get expired secrets
     */
    public function getExpiredSecrets(Client $client): array
    {
        return ClientSecret::where('client_id', $client->id)
            ->where('is_active', true)
            ->where('expires_at', '<', now())
            ->select(['key', 'expires_at', 'metadata'])
            ->get()
            ->toArray();
    }
    
    /**
     * Clean up expired secrets
     */
    public function cleanupExpiredSecrets(Client $client): int
    {
        $count = ClientSecret::where('client_id', $client->id)
            ->where('is_active', true)
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);
        
        Log::info('Cleaned up expired secrets', [
            'client_id' => $client->id,
            'count' => $count,
        ]);
        
        return $count;
    }
    
    /**
     * Generate a secret value based on type
     */
    private function generateSecretValue(string $type): string
    {
        return match ($type) {
            'api_key' => 'sk_' . Str::random(32),
            'password' => Str::password(16),
            'token' => Str::random(64),
            'uuid' => Str::uuid(),
            'jwt_secret' => base64_encode(random_bytes(32)),
            'webhook_secret' => 'whsec_' . Str::random(32),
            default => Str::random(32),
        };
    }
    
    /**
     * Get cache key for secret
     */
    private function getCacheKey(int $clientId, string $key): string
    {
        return self::CACHE_PREFIX . $clientId . '_' . $key;
    }
    
    /**
     * Clear secret cache
     */
    private function clearSecretCache(int $clientId, string $key): void
    {
        Cache::forget($this->getCacheKey($clientId, $key));
    }
    
    /**
     * Validate secret key format
     */
    public function validateSecretKey(string $key): bool
    {
        // Secret keys must be alphanumeric with underscores and dots
        return preg_match('/^[a-zA-Z0-9_\.]+$/', $key) && strlen($key) <= 255;
    }
    
    /**
     * Get secret usage statistics
     */
    public function getSecretUsageStats(Client $client): array
    {
        $secrets = ClientSecret::where('client_id', $client->id)
            ->where('is_active', true)
            ->get();
        
        $stats = [
            'total_secrets' => $secrets->count(),
            'never_used' => $secrets->whereNull('last_used_at')->count(),
            'used_recently' => $secrets->where('last_used_at', '>=', now()->subDays(7))->count(),
            'expired' => $secrets->where('expires_at', '<', now())->count(),
            'expiring_soon' => $secrets->where('expires_at', '>=', now())
                ->where('expires_at', '<=', now()->addDays(7))
                ->count(),
        ];
        
        return $stats;
    }
    
    /**
     * Export secrets for backup (encrypted)
     */
    public function exportSecrets(Client $client, string $encryptionKey): string
    {
        $secrets = ClientSecret::where('client_id', $client->id)
            ->where('is_active', true)
            ->get()
            ->map(function ($secret) {
                return [
                    'key' => $secret->key,
                    'encrypted_value' => $secret->encrypted_value,
                    'metadata' => $secret->metadata,
                    'created_at' => $secret->created_at,
                    'expires_at' => $secret->expires_at,
                ];
            });
        
        $exportData = [
            'client_id' => $client->id,
            'exported_at' => now()->toISOString(),
            'secrets' => $secrets,
        ];
        
        return Crypt::encrypt(json_encode($exportData));
    }
    
    /**
     * Import secrets from backup
     */
    public function importSecrets(Client $client, string $encryptedData): bool
    {
        try {
            $exportData = json_decode(Crypt::decrypt($encryptedData), true);
            
            if ($exportData['client_id'] !== $client->id) {
                throw new \Exception('Client ID mismatch');
            }
            
            foreach ($exportData['secrets'] as $secretData) {
                ClientSecret::updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'key' => $secretData['key'],
                    ],
                    [
                        'encrypted_value' => $secretData['encrypted_value'],
                        'metadata' => $secretData['metadata'],
                        'created_at' => $secretData['created_at'],
                        'expires_at' => $secretData['expires_at'],
                        'is_active' => true,
                    ]
                );
            }
            
            Log::info('Secrets imported successfully', [
                'client_id' => $client->id,
                'count' => count($exportData['secrets']),
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to import secrets', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
}