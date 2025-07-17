<?php

namespace App\Services;

use App\Models\Script;
use App\Models\ScriptExecutionLog;
use App\Services\Security\ScriptSecurityService;
use App\Services\Security\AstSecurityAnalyzer;
use App\Services\Scripting\ScriptingApiService;
use App\Services\Scripting\ResourceMonitorService;
use App\Exceptions\ScriptExecutionException;
use App\Exceptions\SecurityViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\RequestException;
use Throwable;

class DenoScriptingService
{
    protected ScriptSecurityService $securityService;
    protected AstSecurityAnalyzer $astAnalyzer;
    protected ScriptingApiService $apiService;
    protected ResourceMonitorService $resourceMonitor;
    protected string $denoServiceUrl;

    public function __construct(
        ScriptSecurityService $securityService,
        AstSecurityAnalyzer $astAnalyzer,
        ScriptingApiService $apiService,
        ResourceMonitorService $resourceMonitor
    ) {
        $this->securityService = $securityService;
        $this->astAnalyzer = $astAnalyzer;
        $this->apiService = $apiService;
        $this->resourceMonitor = $resourceMonitor;
        $this->denoServiceUrl = config('scripting.deno.service_url', 'http://deno-executor:8080');
    }

    /**
     * Execute a script with full security and monitoring
     */
    public function executeScript(
        Script $script,
        array $context = [],
        string $triggerType = 'manual',
        ?int $executedBy = null
    ): ScriptExecutionLog {
        // Create execution log
        $executionLog = ScriptExecutionLog::create([
            'script_id' => $script->id,
            'client_id' => $script->client_id,
            'executed_by' => $executedBy,
            'execution_context' => json_encode($context),
            'trigger_type' => $triggerType,
            'trigger_data' => $context,
        ]);

        try {
            // Enhanced security pre-checks with AST analysis
            $this->performSecurityChecks($script, $context, $executionLog);

            // Start execution monitoring
            $executionLog->markAsStarted();
            $this->resourceMonitor->startMonitoring($executionLog->id);

            // Execute the script in Deno sidecar
            $output = $this->executeInDenoSidecar($script, $context, $executionLog);

            // Complete execution
            $resourceUsage = $this->resourceMonitor->getResourceUsage($executionLog->id);
            $executionLog->markAsSuccessful($output, $resourceUsage);

            Log::info('Script executed successfully', [
                'script_id' => $script->id,
                'execution_log_id' => $executionLog->id,
                'execution_time' => $executionLog->execution_time,
                'executor' => 'deno-sidecar',
            ]);

            return $executionLog;

        } catch (SecurityViolationException $e) {
            $this->handleSecurityViolation($executionLog, $e);
            throw $e;
        } catch (ScriptExecutionException $e) {
            $this->handleExecutionError($executionLog, $e);
            throw $e;
        } catch (Throwable $e) {
            $this->handleUnexpectedError($executionLog, $e);
            throw new ScriptExecutionException(
                'Unexpected error during script execution: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        } finally {
            $this->resourceMonitor->stopMonitoring($executionLog->id);
        }
    }

    /**
     * Execute script in Deno sidecar environment
     */
    protected function executeInDenoSidecar(Script $script, array $context, ScriptExecutionLog $executionLog): string
    {
        $startTime = microtime(true);
        
        try {
            $config = config('scripting.execution');
            
            // Prepare execution request
            $executionRequest = [
                'code' => $script->code,
                'context' => $context,
                'timeout' => ($script->getConfigValue('time_limit', $config['timeout']) * 1000), // Convert to milliseconds
                'memory_limit' => ($script->getConfigValue('memory_limit', $config['memory_limit']) * 1024 * 1024), // Convert to bytes
                'client_id' => $script->client_id,
                'script_id' => $script->id,
                'execution_id' => $executionLog->id,
            ];

            // Make request to Deno sidecar
            $response = Http::timeout(($config['timeout'] + 5))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Execution-ID' => $executionLog->id,
                ])
                ->post($this->denoServiceUrl . '/execute', $executionRequest);

            if (!$response->successful()) {
                throw new ScriptExecutionException(
                    'Deno sidecar request failed: ' . $response->body(),
                    $response->status()
                );
            }

            $responseData = $response->json();
            
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            $executionLog->update([
                'execution_time' => $executionTime,
                'memory_used' => $responseData['memory_used'] ?? 0,
                'output' => json_encode($responseData['output'] ?? []),
            ]);

            if (!$responseData['success']) {
                throw new ScriptExecutionException(
                    'Script execution failed: ' . $responseData['error'],
                    500
                );
            }

            return is_string($responseData['result']) ? $responseData['result'] : json_encode($responseData['result']);
            
        } catch (RequestException $e) {
            $executionTime = microtime(true) - $startTime;
            $executionLog->update(['execution_time' => $executionTime]);
            
            throw new ScriptExecutionException(
                'Deno sidecar communication error: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Perform comprehensive security checks before execution
     */
    protected function performSecurityChecks(Script $script, array $context, ScriptExecutionLog $executionLog): void
    {
        // Check script permissions
        if (!$this->securityService->canExecuteScript($script, auth()->user())) {
            throw new SecurityViolationException('Insufficient permissions to execute script');
        }

        // Check rate limits
        if ($this->securityService->hasExceededRateLimit($script->client)) {
            $executionLog->addSecurityFlag('rate_limit', 'Rate limit exceeded');
            throw new SecurityViolationException('Rate limit exceeded');
        }

        // Enhanced AST-based security validation
        $astAnalysis = $this->astAnalyzer->analyze($script->code);
        
        if (!empty($astAnalysis['issues'])) {
            $highSeverityIssues = array_filter($astAnalysis['issues'], function ($issue) {
                return $issue['severity'] === 'high';
            });

            if (!empty($highSeverityIssues)) {
                foreach ($highSeverityIssues as $issue) {
                    $executionLog->addSecurityFlag('ast_analysis', $issue['message']);
                }
                throw new SecurityViolationException('Script contains high-severity security issues');
            }

            // Log medium/low severity issues for monitoring
            foreach ($astAnalysis['issues'] as $issue) {
                if ($issue['severity'] !== 'high') {
                    $executionLog->addSecurityFlag('ast_warning', $issue['message']);
                }
            }
        }

        // Fallback regex-based validation for additional coverage
        $regexIssues = $this->securityService->validateScriptContent($script->code);
        if (!empty($regexIssues)) {
            foreach ($regexIssues as $issue) {
                $executionLog->addSecurityFlag('regex_validation', $issue);
            }
            throw new SecurityViolationException('Script contains security violations');
        }

        // Check resource availability
        if (!$this->resourceMonitor->hasAvailableResources()) {
            throw new SecurityViolationException('Insufficient system resources');
        }
    }

    /**
     * Handle security violations
     */
    protected function handleSecurityViolation(ScriptExecutionLog $executionLog, SecurityViolationException $e): void
    {
        $executionLog->addSecurityFlag('security_violation', $e->getMessage());
        $executionLog->markAsFailed($e->getMessage());
        
        Log::warning('Security violation during script execution', [
            'execution_log_id' => $executionLog->id,
            'script_id' => $executionLog->script_id,
            'error' => $e->getMessage(),
            'executor' => 'deno-sidecar',
        ]);
    }

    /**
     * Handle script execution errors
     */
    protected function handleExecutionError(ScriptExecutionLog $executionLog, ScriptExecutionException $e): void
    {
        $resourceUsage = $this->resourceMonitor->getResourceUsage($executionLog->id);
        $executionLog->markAsFailed($e->getMessage(), $resourceUsage);
        
        Log::error('Script execution error', [
            'execution_log_id' => $executionLog->id,
            'script_id' => $executionLog->script_id,
            'error' => $e->getMessage(),
            'executor' => 'deno-sidecar',
        ]);
    }

    /**
     * Handle unexpected errors
     */
    protected function handleUnexpectedError(ScriptExecutionLog $executionLog, Throwable $e): void
    {
        $resourceUsage = $this->resourceMonitor->getResourceUsage($executionLog->id);
        $executionLog->markAsFailed('Unexpected error: ' . $e->getMessage(), $resourceUsage);
        
        Log::critical('Unexpected error during script execution', [
            'execution_log_id' => $executionLog->id,
            'script_id' => $executionLog->script_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'executor' => 'deno-sidecar',
        ]);
    }

    /**
     * Validate script syntax without execution
     */
    public function validateScriptSyntax(string $code): array
    {
        try {
            // Use AST analyzer for comprehensive syntax validation
            $astAnalysis = $this->astAnalyzer->analyze($code);
            
            if ($astAnalysis['score'] < 40) {
                return [
                    'valid' => false,
                    'error' => 'Script has critical security issues',
                    'issues' => $astAnalysis['issues'],
                    'score' => $astAnalysis['score'],
                ];
            }

            // Try basic syntax validation via Deno
            $response = Http::timeout(10)->post($this->denoServiceUrl . '/validate', [
                'code' => $code,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'valid' => $responseData['valid'] ?? true,
                    'error' => $responseData['error'] ?? null,
                    'ast_analysis' => $astAnalysis,
                ];
            }

            return [
                'valid' => true,
                'ast_analysis' => $astAnalysis,
            ];

        } catch (Throwable $e) {
            return [
                'valid' => false,
                'error' => 'Syntax validation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get script execution statistics
     */
    public function getExecutionStats(Script $script): array
    {
        $cacheKey = "script_stats_{$script->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($script) {
            return [
                'total_executions' => $script->executionLogs()->count(),
                'successful_executions' => $script->successful_executions,
                'failed_executions' => $script->failed_executions,
                'average_execution_time' => $script->average_execution_time,
                'last_execution' => $script->executionLogs()
                    ->orderBy('created_at', 'desc')
                    ->first()?->created_at,
                'executor' => 'deno-sidecar',
            ];
        });
    }

    /**
     * Check if Deno sidecar is available
     */
    public function isDenoSidecarAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->denoServiceUrl . '/health');
            return $response->successful() && $response->body() === 'OK';
        } catch (Throwable $e) {
            Log::error('Deno sidecar health check failed', [
                'error' => $e->getMessage(),
                'service_url' => $this->denoServiceUrl,
            ]);
            return false;
        }
    }
}