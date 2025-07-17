<?php

namespace App\Services\Security;

use App\Models\Script;
use App\Models\Client;
use App\Models\User;
use App\Services\Scripting\ResourceMonitorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

class ScriptSecurityService
{
    protected ResourceMonitorService $resourceMonitor;
    protected array $forbiddenPatterns;
    protected array $allowedTables;
    protected array $allowedDomains;

    public function __construct(ResourceMonitorService $resourceMonitor)
    {
        $this->resourceMonitor = $resourceMonitor;
        $this->initializeSecurityConfig();
    }

    /**
     * Initialize security configuration
     */
    protected function initializeSecurityConfig(): void
    {
        $this->forbiddenPatterns = [
            // Dangerous JavaScript patterns
            '/eval\s*\(/i',
            '/Function\s*\(/i',
            '/setTimeout\s*\(/i',
            '/setInterval\s*\(/i',
            '/new\s+Function/i',
            '/window\./i',
            '/document\./i',
            '/XMLHttpRequest/i',
            '/fetch\s*\(/i',
            '/import\s*\(/i',
            '/require\s*\(/i',
            '/process\./i',
            '/global\./i',
            '/__proto__/i',
            '/constructor\./i',
            '/prototype\./i',
            
            // Potential security risks
            '/delete\s+/i',
            '/with\s*\(/i',
            '/arguments\./i',
            '/caller\./i',
            '/callee\./i',
            
            // File system access attempts
            '/fs\./i',
            '/path\./i',
            '/os\./i',
            '/child_process/i',
            '/exec\s*\(/i',
            '/spawn\s*\(/i',
            
            // Network access attempts
            '/net\./i',
            '/http\./i',
            '/https\./i',
            '/url\./i',
            '/dns\./i',
            
            // Dangerous string patterns
            '/\<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/onclick=/i',
        ];

        $this->allowedTables = config('scripting.database.allowed_tables', []);
        $this->allowedDomains = config('scripting.http.allowed_domains', []);
    }

    /**
     * Check if user can execute script
     */
    public function canExecuteScript(Script $script, ?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Check if script is active
        if (!$script->is_active) {
            return false;
        }

        // Check if user belongs to the same client
        if ($user->client_id !== $script->client_id) {
            return false;
        }

        // Check user permissions
        if (!$user->can('execute-scripts')) {
            return false;
        }

        // Check client-specific permissions
        if (!$this->hasClientPermission($script->client, 'script.execute')) {
            return false;
        }

        return true;
    }

    /**
     * Check if client has exceeded rate limit
     */
    public function hasExceededRateLimit(Client $client): bool
    {
        $key = "rate_limit:client:{$client->id}";
        $limit = $client->rate_limit;
        $window = 60; // 1 minute window
        
        return RateLimiter::tooManyAttempts($key, $limit);
    }

    /**
     * Increment rate limit for client
     */
    public function incrementRateLimit(Client $client): void
    {
        $key = "rate_limit:client:{$client->id}";
        $window = 60; // 1 minute window
        
        RateLimiter::hit($key, $window);
    }

    /**
     * Validate script content for security issues
     */
    public function validateScriptContent(string $code): array
    {
        $issues = [];

        // Check for forbidden patterns
        foreach ($this->forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $code)) {
                $issues[] = "Forbidden pattern detected: " . $pattern;
            }
        }

        // Check for suspicious string lengths
        if (strlen($code) > 65535) {
            $issues[] = "Script code exceeds maximum length";
        }

        // Check for excessive nesting
        if ($this->hasExcessiveNesting($code)) {
            $issues[] = "Script has excessive nesting depth";
        }

        // Check for potential infinite loops
        if ($this->hasPotentialInfiniteLoop($code)) {
            $issues[] = "Script contains potential infinite loop";
        }

        // Check for encoded content
        if ($this->hasEncodedContent($code)) {
            $issues[] = "Script contains encoded or obfuscated content";
        }

        return $issues;
    }

    /**
     * Check if context variable is valid
     */
    public function isValidContextVariable(string $key, $value): bool
    {
        // Check key format
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
            return false;
        }

        // Check for reserved words
        $reservedWords = ['api', 'console', 'window', 'document', 'global', 'process'];
        if (in_array($key, $reservedWords)) {
            return false;
        }

        // Check value type
        if (is_object($value) || is_resource($value)) {
            return false;
        }

        return true;
    }

    /**
     * Check if script can access database
     */
    public function canAccessDatabase(Script $script): bool
    {
        // Check global database access permission
        if (!$this->hasClientPermission($script->client, 'database.access')) {
            return false;
        }

        // Check script-specific database permission
        return $script->getConfigValue('permissions.database', false);
    }

    /**
     * Check if script can access specific table
     */
    public function canAccessTable(Script $script, string $table): bool
    {
        if (!$this->canAccessDatabase($script)) {
            return false;
        }

        // Check if table is in allowed list
        if (!empty($this->allowedTables) && !in_array($table, $this->allowedTables)) {
            return false;
        }

        // Check client-specific table permissions
        $allowedTables = $script->client->getSetting('database.allowed_tables', []);
        if (!empty($allowedTables) && !in_array($table, $allowedTables)) {
            return false;
        }

        return true;
    }

    /**
     * Check if script can access URL
     */
    public function canAccessUrl(Script $script, string $url): bool
    {
        // Check global HTTP access permission
        if (!$this->hasClientPermission($script->client, 'http.access')) {
            return false;
        }

        // Check script-specific HTTP permission
        if (!$script->getConfigValue('permissions.http', false)) {
            return false;
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check domain restrictions
        $parsedUrl = parse_url($url);
        $domain = $parsedUrl['host'] ?? '';

        // Check against allowed domains
        if (!empty($this->allowedDomains) && !$this->isDomainAllowed($domain, $this->allowedDomains)) {
            return false;
        }

        // Check client-specific domain restrictions
        $clientAllowedDomains = $script->client->getSetting('http.allowed_domains', []);
        if (!empty($clientAllowedDomains) && !$this->isDomainAllowed($domain, $clientAllowedDomains)) {
            return false;
        }

        // Block internal/private IP addresses
        if ($this->isInternalIp($domain)) {
            return false;
        }

        return true;
    }

    /**
     * Check if script can dispatch event
     */
    public function canDispatchEvent(Script $script, string $eventName): bool
    {
        // Check global event dispatch permission
        if (!$this->hasClientPermission($script->client, 'events.dispatch')) {
            return false;
        }

        // Check script-specific event permission
        if (!$script->getConfigValue('permissions.events', false)) {
            return false;
        }

        // Check against allowed events
        $allowedEvents = $script->client->getSetting('events.allowed_events', []);
        if (!empty($allowedEvents) && !in_array($eventName, $allowedEvents)) {
            return false;
        }

        return true;
    }

    /**
     * Check if client has specific permission
     */
    protected function hasClientPermission(Client $client, string $permission): bool
    {
        $permissions = $client->getSetting('permissions', []);
        return in_array($permission, $permissions);
    }

    /**
     * Check if domain is allowed
     */
    protected function isDomainAllowed(string $domain, array $allowedDomains): bool
    {
        foreach ($allowedDomains as $allowedDomain) {
            if ($domain === $allowedDomain) {
                return true;
            }
            
            // Check wildcard domains
            if (str_starts_with($allowedDomain, '*.')) {
                $pattern = str_replace('*.', '', $allowedDomain);
                if (str_ends_with($domain, $pattern)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if IP is internal/private
     */
    protected function isInternalIp(string $host): bool
    {
        $ip = gethostbyname($host);
        
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Check for excessive nesting in code
     */
    protected function hasExcessiveNesting(string $code): bool
    {
        $maxDepth = 10;
        $depth = 0;
        $maxDepthFound = 0;
        
        for ($i = 0; $i < strlen($code); $i++) {
            if ($code[$i] === '{') {
                $depth++;
                $maxDepthFound = max($maxDepthFound, $depth);
            } elseif ($code[$i] === '}') {
                $depth--;
            }
        }
        
        return $maxDepthFound > $maxDepth;
    }

    /**
     * Check for potential infinite loops
     */
    protected function hasPotentialInfiniteLoop(string $code): bool
    {
        // Look for common infinite loop patterns
        $patterns = [
            '/while\s*\(\s*true\s*\)/i',
            '/for\s*\(\s*;\s*;\s*\)/i',
            '/while\s*\(\s*1\s*\)/i',
            '/do\s*\{[^}]*\}\s*while\s*\(\s*true\s*\)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for encoded content
     */
    protected function hasEncodedContent(string $code): bool
    {
        // Check for base64 encoded strings
        if (preg_match('/[A-Za-z0-9+\/]{50,}={0,2}/', $code)) {
            return true;
        }
        
        // Check for hex encoded strings
        if (preg_match('/\\x[0-9a-fA-F]{2,}/', $code)) {
            return true;
        }
        
        // Check for unicode escape sequences
        if (preg_match('/\\u[0-9a-fA-F]{4,}/', $code)) {
            return true;
        }
        
        return false;
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $type, string $message, array $context = []): void
    {
        Log::channel('security')->warning("Security event: {$type}", [
            'message' => $message,
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get security score for script
     */
    public function getSecurityScore(Script $script): float
    {
        $score = 100.0;
        
        // Deduct points for security issues
        $issues = $this->validateScriptContent($script->code);
        $score -= count($issues) * 10;
        
        // Deduct points for excessive permissions
        $permissions = $script->configuration['permissions'] ?? [];
        $score -= count($permissions) * 5;
        
        // Deduct points for failed executions
        $failureRate = $script->failed_executions / max($script->executionLogs()->count(), 1);
        $score -= $failureRate * 20;
        
        return max($score, 0);
    }

    /**
     * Generate security report for script
     */
    public function generateSecurityReport(Script $script): array
    {
        $issues = $this->validateScriptContent($script->code);
        $score = $this->getSecurityScore($script);
        
        return [
            'script_id' => $script->id,
            'security_score' => $score,
            'risk_level' => $this->getRiskLevel($score),
            'issues' => $issues,
            'recommendations' => $this->getSecurityRecommendations($script, $issues),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get risk level based on security score
     */
    protected function getRiskLevel(float $score): string
    {
        if ($score >= 80) {
            return 'low';
        } elseif ($score >= 60) {
            return 'medium';
        } elseif ($score >= 40) {
            return 'high';
        } else {
            return 'critical';
        }
    }

    /**
     * Get security recommendations
     */
    protected function getSecurityRecommendations(Script $script, array $issues): array
    {
        $recommendations = [];
        
        if (!empty($issues)) {
            $recommendations[] = 'Review and fix identified security issues';
        }
        
        if ($script->getConfigValue('permissions.database', false)) {
            $recommendations[] = 'Consider limiting database access to specific tables';
        }
        
        if ($script->getConfigValue('permissions.http', false)) {
            $recommendations[] = 'Restrict HTTP access to specific domains';
        }
        
        if ($script->failed_executions > 5) {
            $recommendations[] = 'Investigate frequent execution failures';
        }
        
        return $recommendations;
    }
}