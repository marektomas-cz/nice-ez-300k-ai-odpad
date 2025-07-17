<?php

namespace App\Services\Monitoring;

use App\Models\ScriptExecutionLog;
use App\Services\Scripting\ResourceMonitorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Exception;

class WatchdogService
{
    protected ResourceMonitorService $resourceMonitor;
    protected KillSwitchService $killSwitch;
    protected PrometheusMetricsService $metricsService;
    protected array $config;

    public function __construct(
        ResourceMonitorService $resourceMonitor,
        KillSwitchService $killSwitch,
        PrometheusMetricsService $metricsService
    ) {
        $this->resourceMonitor = $resourceMonitor;
        $this->killSwitch = $killSwitch;
        $this->metricsService = $metricsService;
        $this->config = config('scripting.watchdog', []);
    }

    /**
     * Monitor and enforce resource limits
     */
    public function monitorExecution(int $executionLogId): void
    {
        $executionLog = ScriptExecutionLog::find($executionLogId);
        
        if (!$executionLog || $executionLog->status !== 'running') {
            return;
        }

        $startTime = microtime(true);
        $monitoringInterval = $this->config['monitoring_interval'] ?? 1; // seconds
        
        while (true) {
            try {
                // Check if execution is still running
                $executionLog->refresh();
                if ($executionLog->status !== 'running') {
                    break;
                }

                // Check resource limits
                $violations = $this->resourceMonitor->hasExceededLimits($executionLogId);
                
                if (!empty($violations)) {
                    $this->handleResourceViolations($executionLog, $violations);
                    break;
                }

                // Check if kill switch should be triggered
                if ($this->killSwitch->shouldTriggerKillSwitch()) {
                    Log::warning('Watchdog triggering kill switch', [
                        'execution_id' => $executionLogId,
                        'script_id' => $executionLog->script_id,
                    ]);
                    
                    $violations = $this->killSwitch->checkThresholds();
                    $this->killSwitch->triggerKillSwitch($violations);
                    break;
                }

                // Update metrics
                $this->updateWatchdogMetrics($executionLog);

                // Sleep for monitoring interval
                sleep($monitoringInterval);

            } catch (Exception $e) {
                Log::error('Watchdog monitoring error', [
                    'execution_id' => $executionLogId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                break;
            }
        }
    }

    /**
     * Handle resource violations
     */
    protected function handleResourceViolations(ScriptExecutionLog $executionLog, array $violations): void
    {
        Log::warning('Resource violations detected - terminating execution', [
            'execution_id' => $executionLog->id,
            'script_id' => $executionLog->script_id,
            'violations' => $violations,
        ]);

        // Mark execution as terminated
        $executionLog->update([
            'status' => 'terminated',
            'error_message' => 'Execution terminated due to resource violations: ' . implode(', ', $violations),
            'ended_at' => now(),
        ]);

        // Stop Deno execution
        $this->stopDenoExecution($executionLog);

        // Update metrics
        $this->metricsService->incrementTerminatedExecutions();
        
        foreach ($violations as $violation) {
            $this->metricsService->incrementResourceViolations($violation);
        }

        // Send alert
        $this->sendResourceViolationAlert($executionLog, $violations);
    }

    /**
     * Stop Deno execution
     */
    protected function stopDenoExecution(ScriptExecutionLog $executionLog): void
    {
        try {
            $denoServiceUrl = config('scripting.deno.service_url', 'http://deno-executor:8080');
            
            $response = Http::timeout(5)->post($denoServiceUrl . '/stop', [
                'execution_id' => $executionLog->id,
            ]);

            if ($response->successful()) {
                Log::info('Successfully stopped Deno execution', [
                    'execution_id' => $executionLog->id,
                ]);
            } else {
                Log::warning('Failed to stop Deno execution', [
                    'execution_id' => $executionLog->id,
                    'response' => $response->body(),
                ]);
            }
            
        } catch (Exception $e) {
            Log::error('Error stopping Deno execution', [
                'execution_id' => $executionLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update watchdog metrics
     */
    protected function updateWatchdogMetrics(ScriptExecutionLog $executionLog): void
    {
        $resourceUsage = $this->resourceMonitor->getResourceUsage($executionLog->id);
        
        $this->metricsService->updateExecutionMetrics([
            'execution_id' => $executionLog->id,
            'script_id' => $executionLog->script_id,
            'client_id' => $executionLog->client_id,
            'execution_time' => $resourceUsage['execution_time'],
            'memory_used' => $resourceUsage['memory_used'],
            'peak_memory' => $resourceUsage['peak_memory'],
            'cpu_usage' => $resourceUsage['cpu_usage'],
        ]);
    }

    /**
     * Send resource violation alert
     */
    protected function sendResourceViolationAlert(ScriptExecutionLog $executionLog, array $violations): void
    {
        $alertData = [
            'type' => 'resource_violation',
            'execution_id' => $executionLog->id,
            'script_id' => $executionLog->script_id,
            'client_id' => $executionLog->client_id,
            'violations' => $violations,
            'timestamp' => now()->toISOString(),
            'severity' => 'warning',
        ];

        // Send to configured alert channels
        $this->sendSlackAlert($alertData);
        $this->sendWebhookAlert($alertData);
    }

    /**
     * Send Slack alert
     */
    protected function sendSlackAlert(array $alertData): void
    {
        $slackWebhook = config('scripting.alerts.slack_webhook');
        
        if (!$slackWebhook) {
            return;
        }

        try {
            $violationsText = implode(', ', $alertData['violations']);

            $message = [
                'text' => '⚠️ Resource Violation Detected',
                'attachments' => [
                    [
                        'color' => 'warning',
                        'title' => 'Script Execution Terminated',
                        'text' => "Script execution was terminated due to resource violations.\n\n" .
                                "**Script ID:** {$alertData['script_id']}\n" .
                                "**Execution ID:** {$alertData['execution_id']}\n" .
                                "**Violations:** {$violationsText}\n" .
                                "**Timestamp:** {$alertData['timestamp']}",
                        'footer' => 'Resource Watchdog',
                        'ts' => now()->timestamp,
                    ],
                ],
            ];

            Http::timeout(10)->post($slackWebhook, $message);
            
        } catch (Exception $e) {
            Log::error('Failed to send Slack alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send webhook alert
     */
    protected function sendWebhookAlert(array $alertData): void
    {
        $webhookUrl = config('scripting.alerts.webhook_url');
        
        if (!$webhookUrl) {
            return;
        }

        try {
            Http::timeout(10)->post($webhookUrl, $alertData);
        } catch (Exception $e) {
            Log::error('Failed to send webhook alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Start watchdog monitoring in background
     */
    public function startBackgroundMonitoring(int $executionLogId): void
    {
        // This would typically be run in a background job/process
        // For now, we'll use a simple approach
        $pid = pcntl_fork();
        
        if ($pid == 0) {
            // Child process - run monitoring
            $this->monitorExecution($executionLogId);
            exit(0);
        } elseif ($pid > 0) {
            // Parent process - continue
            Log::info('Started watchdog monitoring in background', [
                'execution_id' => $executionLogId,
                'pid' => $pid,
            ]);
        } else {
            // Fork failed
            Log::error('Failed to fork watchdog process', [
                'execution_id' => $executionLogId,
            ]);
        }
    }

    /**
     * Check system health
     */
    public function checkSystemHealth(): array
    {
        $metrics = $this->resourceMonitor->getSystemMetrics();
        $killSwitchStatus = $this->killSwitch->getKillSwitchStatus();
        
        return [
            'system_metrics' => $metrics,
            'kill_switch' => $killSwitchStatus,
            'watchdog_active' => true,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get watchdog statistics
     */
    public function getWatchdogStats(): array
    {
        $cacheKey = 'watchdog_stats';
        
        return Cache::remember($cacheKey, 60, function () {
            return [
                'total_monitored_executions' => Cache::get('watchdog_total_monitored', 0),
                'total_terminated_executions' => Cache::get('watchdog_total_terminated', 0),
                'total_resource_violations' => Cache::get('watchdog_total_violations', 0),
                'active_monitors' => Cache::get('watchdog_active_monitors', 0),
                'last_violation' => Cache::get('watchdog_last_violation'),
                'uptime' => Cache::get('watchdog_uptime', 0),
            ];
        });
    }
}