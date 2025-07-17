<?php

namespace App\Services;

use App\Models\Script;
use App\Models\ScriptExecutionLog;
use App\Services\Security\ScriptSecurityService;
use App\Services\Scripting\ScriptingApiService;
use App\Services\Scripting\ResourceMonitorService;
use App\Exceptions\ScriptExecutionException;
use App\Exceptions\SecurityViolationException;
use V8Js;
use V8JsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ScriptingService
{
    protected ScriptSecurityService $securityService;
    protected ScriptingApiService $apiService;
    protected ResourceMonitorService $resourceMonitor;

    public function __construct(
        ScriptSecurityService $securityService,
        ScriptingApiService $apiService,
        ResourceMonitorService $resourceMonitor
    ) {
        $this->securityService = $securityService;
        $this->apiService = $apiService;
        $this->resourceMonitor = $resourceMonitor;
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
            // Security pre-checks
            $this->performSecurityChecks($script, $context, $executionLog);

            // Start execution monitoring
            $executionLog->markAsStarted();
            $this->resourceMonitor->startMonitoring($executionLog->id);

            // Execute the script
            $output = $this->executeInSandbox($script, $context, $executionLog);

            // Complete execution
            $resourceUsage = $this->resourceMonitor->getResourceUsage($executionLog->id);
            $executionLog->markAsSuccessful($output, $resourceUsage);

            Log::info('Script executed successfully', [
                'script_id' => $script->id,
                'execution_log_id' => $executionLog->id,
                'execution_time' => $executionLog->execution_time,
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
     * Execute script in V8Js sandbox environment
     */
    protected function executeInSandbox(Script $script, array $context, ScriptExecutionLog $executionLog): string
    {
        $startTime = microtime(true);
        
        try {
            // Create V8Js instance with security constraints
            $v8 = new V8Js('PHP', [], [], true, true);
            
            // Set resource limits
            $this->setResourceLimits($v8, $script);
            
            // Inject secure API
            $this->injectSecureApi($v8, $script, $context, $executionLog);
            
            // Prepare script code with security wrapper
            $secureCode = $this->wrapScriptCode($script->code, $context);
            
            // Execute script
            $result = $v8->executeString($secureCode, $script->name);
            
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            $executionLog->update(['execution_time' => $executionTime]);
            
            return is_string($result) ? $result : json_encode($result);
            
        } catch (V8JsException $e) {
            $executionTime = microtime(true) - $startTime;
            $executionLog->update(['execution_time' => $executionTime]);
            
            throw new ScriptExecutionException(
                'V8Js execution error: ' . $e->getMessage(),
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

        // Validate script content
        $securityIssues = $this->securityService->validateScriptContent($script->code);
        if (!empty($securityIssues)) {
            foreach ($securityIssues as $issue) {
                $executionLog->addSecurityFlag('content_validation', $issue);
            }
            throw new SecurityViolationException('Script contains security violations');
        }

        // Check resource availability
        if (!$this->resourceMonitor->hasAvailableResources()) {
            throw new SecurityViolationException('Insufficient system resources');
        }
    }

    /**
     * Set resource limits for V8Js execution
     */
    protected function setResourceLimits(V8Js $v8, Script $script): void
    {
        $config = config('scripting.execution');
        
        // Set memory limit
        $memoryLimit = $script->getConfigValue('memory_limit', $config['memory_limit']) * 1024 * 1024;
        $v8->setMemoryLimit($memoryLimit);
        
        // Set execution time limit
        $timeLimit = $script->getConfigValue('time_limit', $config['timeout']) * 1000;
        $v8->setTimeLimit($timeLimit);
        
        // Set average object size
        $v8->setAverageObjectSize(100);
    }

    /**
     * Inject secure API into V8Js context
     */
    protected function injectSecureApi(V8Js $v8, Script $script, array $context, ScriptExecutionLog $executionLog): void
    {
        // Create secure API instance
        $api = $this->apiService->createSecureApi($script, $context, $executionLog);
        
        // Inject API into V8Js context
        $v8->api = $api;
        
        // Inject context variables
        foreach ($context as $key => $value) {
            if ($this->securityService->isValidContextVariable($key, $value)) {
                $v8->$key = $value;
            }
        }
    }

    /**
     * Wrap script code with security and monitoring
     */
    protected function wrapScriptCode(string $code, array $context): string
    {
        $wrapper = '
            (function() {
                "use strict";
                
                // Security restrictions
                delete this.eval;
                delete this.Function;
                delete this.Object.constructor;
                delete this.Array.constructor;
                
                // Execution wrapper
                try {
                    ' . $code . '
                } catch (error) {
                    throw new Error("Script execution error: " + error.message);
                }
            })();
        ';
        
        return $wrapper;
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
        ]);
    }

    /**
     * Validate script syntax without execution
     */
    public function validateScriptSyntax(string $code): array
    {
        try {
            $v8 = new V8Js();
            $v8->executeString('(' . $code . ')');
            return ['valid' => true];
        } catch (V8JsException $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'line' => $e->getJsLineNumber(),
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
            ];
        });
    }
}