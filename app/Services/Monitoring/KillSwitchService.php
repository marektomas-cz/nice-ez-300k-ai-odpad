<?php

namespace App\Services\Monitoring;

use App\Models\ScriptExecutionLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

class KillSwitchService
{
    protected array $config;
    protected array $thresholds;
    protected PrometheusMetricsService $metricsService;

    public function __construct(PrometheusMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
        $this->config = config('scripting.kill_switch', []);
        $this->initializeThresholds();
    }

    /**
     * Initialize monitoring thresholds
     */
    protected function initializeThresholds(): void
    {
        $this->thresholds = [
            'memory_usage' => $this->config['memory_threshold'] ?? 80, // 80% of available memory
            'cpu_usage' => $this->config['cpu_threshold'] ?? 85, // 85% of available CPU
            'execution_time' => $this->config['execution_time_threshold'] ?? 60, // 60 seconds
            'concurrent_executions' => $this->config['concurrent_threshold'] ?? 10, // 10 concurrent executions
            'failed_executions_rate' => $this->config['failure_rate_threshold'] ?? 50, // 50% failure rate
            'error_rate_per_minute' => $this->config['error_rate_threshold'] ?? 5, // 5 errors per minute
        ];
    }

    /**
     * Check if kill switch should be triggered
     */
    public function shouldTriggerKillSwitch(): bool
    {
        $violations = $this->checkThresholds();
        
        if (!empty($violations)) {
            Log::warning('Kill switch thresholds violated', [
                'violations' => $violations,
                'timestamp' => now()->toISOString(),
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Check all monitoring thresholds
     */
    protected function checkThresholds(): array
    {
        $violations = [];
        
        // Check memory usage
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage > $this->thresholds['memory_usage']) {
            $violations[] = [
                'type' => 'memory_usage',
                'current' => $memoryUsage,
                'threshold' => $this->thresholds['memory_usage'],
                'message' => "Memory usage ({$memoryUsage}%) exceeds threshold ({$this->thresholds['memory_usage']}%)",
            ];
        }

        // Check CPU usage
        $cpuUsage = $this->getCpuUsage();
        if ($cpuUsage > $this->thresholds['cpu_usage']) {
            $violations[] = [
                'type' => 'cpu_usage',
                'current' => $cpuUsage,
                'threshold' => $this->thresholds['cpu_usage'],
                'message' => "CPU usage ({$cpuUsage}%) exceeds threshold ({$this->thresholds['cpu_usage']}%)",
            ];
        }

        // Check concurrent executions
        $concurrentExecutions = $this->getConcurrentExecutions();
        if ($concurrentExecutions > $this->thresholds['concurrent_executions']) {
            $violations[] = [
                'type' => 'concurrent_executions',
                'current' => $concurrentExecutions,
                'threshold' => $this->thresholds['concurrent_executions'],
                'message' => "Concurrent executions ({$concurrentExecutions}) exceeds threshold ({$this->thresholds['concurrent_executions']})",
            ];
        }

        // Check execution time violations
        $longRunningExecutions = $this->getLongRunningExecutions();
        if ($longRunningExecutions > 0) {
            $violations[] = [
                'type' => 'execution_time',
                'current' => $longRunningExecutions,
                'threshold' => $this->thresholds['execution_time'],
                'message' => "Found {$longRunningExecutions} executions exceeding {$this->thresholds['execution_time']} seconds",
            ];
        }

        // Check failure rate
        $failureRate = $this->getFailureRate();
        if ($failureRate > $this->thresholds['failed_executions_rate']) {
            $violations[] = [
                'type' => 'failure_rate',
                'current' => $failureRate,
                'threshold' => $this->thresholds['failed_executions_rate'],
                'message' => "Failure rate ({$failureRate}%) exceeds threshold ({$this->thresholds['failed_executions_rate']}%)",
            ];
        }

        // Check error rate per minute
        $errorRate = $this->getErrorRatePerMinute();
        if ($errorRate > $this->thresholds['error_rate_per_minute']) {
            $violations[] = [
                'type' => 'error_rate',
                'current' => $errorRate,
                'threshold' => $this->thresholds['error_rate_per_minute'],
                'message' => "Error rate ({$errorRate} errors/min) exceeds threshold ({$this->thresholds['error_rate_per_minute']} errors/min)",
            ];
        }

        return $violations;
    }

    /**
     * Trigger kill switch - stop all running executions
     */
    public function triggerKillSwitch(array $violations): bool
    {
        $killSwitchActive = Cache::get('kill_switch_active', false);
        
        if ($killSwitchActive) {
            Log::info('Kill switch already active, skipping trigger');
            return true;
        }

        // Activate kill switch
        Cache::put('kill_switch_active', true, 300); // 5 minutes cooldown
        
        Log::critical('Kill switch triggered - stopping all script executions', [
            'violations' => $violations,
            'timestamp' => now()->toISOString(),
        ]);

        // Stop all running executions
        $stoppedExecutions = $this->stopAllRunningExecutions();
        
        // Send alerts
        $this->sendKillSwitchAlerts($violations, $stoppedExecutions);
        
        // Update metrics
        $this->metricsService->incrementKillSwitchTriggers();
        
        // Fire event
        Event::dispatch('kill.switch.triggered', [
            'violations' => $violations,
            'stopped_executions' => $stoppedExecutions,
            'timestamp' => now(),
        ]);

        return true;
    }

    /**
     * Stop all running script executions
     */
    protected function stopAllRunningExecutions(): int
    {
        $runningExecutions = ScriptExecutionLog::where('status', 'running')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->get();

        $stoppedCount = 0;
        
        foreach ($runningExecutions as $execution) {
            try {
                // Mark execution as killed
                $execution->update([
                    'status' => 'killed',
                    'error_message' => 'Execution stopped by kill switch',
                    'ended_at' => now(),
                ]);

                // Try to stop Deno executor process if possible
                $this->stopDenoExecution($execution);
                
                $stoppedCount++;
                
                Log::info('Stopped execution due to kill switch', [
                    'execution_id' => $execution->id,
                    'script_id' => $execution->script_id,
                    'client_id' => $execution->client_id,
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to stop execution', [
                    'execution_id' => $execution->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stoppedCount;
    }

    /**
     * Attempt to stop Deno execution
     */
    protected function stopDenoExecution(ScriptExecutionLog $execution): void
    {
        try {
            $denoServiceUrl = config('scripting.deno.service_url', 'http://deno-executor:8080');
            
            Http::timeout(5)->post($denoServiceUrl . '/stop', [
                'execution_id' => $execution->id,
            ]);
            
        } catch (\Exception $e) {
            Log::warning('Failed to stop Deno execution', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send kill switch alerts
     */
    protected function sendKillSwitchAlerts(array $violations, int $stoppedExecutions): void
    {
        $alertData = [
            'type' => 'kill_switch_triggered',
            'violations' => $violations,
            'stopped_executions' => $stoppedExecutions,
            'timestamp' => now()->toISOString(),
            'severity' => 'critical',
        ];

        // Send to configured alert channels
        $this->sendSlackAlert($alertData);
        $this->sendEmailAlert($alertData);
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
            $violationsText = collect($alertData['violations'])
                ->map(fn($v) => "• {$v['message']}")
                ->join("\n");

            $message = [
                'text' => '🚨 Kill Switch Triggered',
                'attachments' => [
                    [
                        'color' => 'danger',
                        'title' => 'Script Execution Kill Switch Activated',
                        'text' => "The kill switch has been triggered due to threshold violations.\n\n" .
                                "**Violations:**\n{$violationsText}\n\n" .
                                "**Stopped Executions:** {$alertData['stopped_executions']}\n" .
                                "**Timestamp:** {$alertData['timestamp']}",
                        'footer' => 'Script Execution Monitor',
                        'ts' => now()->timestamp,
                    ],
                ],
            ];

            Http::timeout(10)->post($slackWebhook, $message);
            
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email alert
     */
    protected function sendEmailAlert(array $alertData): void
    {
        // Implementation would depend on your email service
        // This is a placeholder for email notification
        Log::info('Email alert would be sent', $alertData);
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
        } catch (\Exception $e) {
            Log::error('Failed to send webhook alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if kill switch is active
     */
    public function isKillSwitchActive(): bool
    {
        return Cache::get('kill_switch_active', false);
    }

    /**
     * Deactivate kill switch
     */
    public function deactivateKillSwitch(): bool
    {
        Cache::forget('kill_switch_active');
        
        Log::info('Kill switch deactivated', [
            'timestamp' => now()->toISOString(),
        ]);
        
        Event::dispatch('kill.switch.deactivated', [
            'timestamp' => now(),
        ]);

        return true;
    }

    /**
     * Get current memory usage percentage
     */
    protected function getMemoryUsage(): float
    {
        $memInfo = $this->getMemoryInfo();
        
        if ($memInfo['total'] > 0) {
            return (($memInfo['total'] - $memInfo['free']) / $memInfo['total']) * 100;
        }
        
        return 0;
    }

    /**
     * Get memory information
     */
    protected function getMemoryInfo(): array
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $memInfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
            preg_match('/MemFree:\s+(\d+)/', $memInfo, $free);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $available);
            
            return [
                'total' => (int)($total[1] ?? 0),
                'free' => (int)($free[1] ?? 0),
                'available' => (int)($available[1] ?? 0),
            ];
        }
        
        return ['total' => 0, 'free' => 0, 'available' => 0];
    }

    /**
     * Get current CPU usage percentage
     */
    protected function getCpuUsage(): float
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $load = sys_getloadavg();
            $cpuCount = $this->getCpuCount();
            
            if ($cpuCount > 0) {
                return ($load[0] / $cpuCount) * 100;
            }
        }
        
        return 0;
    }

    /**
     * Get number of CPU cores
     */
    protected function getCpuCount(): int
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $cpuInfo = file_get_contents('/proc/cpuinfo');
            return substr_count($cpuInfo, 'processor');
        }
        
        return 1;
    }

    /**
     * Get number of concurrent executions
     */
    protected function getConcurrentExecutions(): int
    {
        return ScriptExecutionLog::where('status', 'running')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();
    }

    /**
     * Get number of long-running executions
     */
    protected function getLongRunningExecutions(): int
    {
        return ScriptExecutionLog::where('status', 'running')
            ->where('created_at', '<=', now()->subSeconds($this->thresholds['execution_time']))
            ->count();
    }

    /**
     * Get failure rate percentage
     */
    protected function getFailureRate(): float
    {
        $totalExecutions = ScriptExecutionLog::where('created_at', '>=', now()->subMinutes(5))
            ->count();
            
        if ($totalExecutions === 0) {
            return 0;
        }
        
        $failedExecutions = ScriptExecutionLog::where('created_at', '>=', now()->subMinutes(5))
            ->where('status', 'failed')
            ->count();
            
        return ($failedExecutions / $totalExecutions) * 100;
    }

    /**
     * Get error rate per minute
     */
    protected function getErrorRatePerMinute(): int
    {
        return ScriptExecutionLog::where('created_at', '>=', now()->subMinute())
            ->where('status', 'failed')
            ->count();
    }

    /**
     * Get kill switch status
     */
    public function getKillSwitchStatus(): array
    {
        $isActive = $this->isKillSwitchActive();
        $violations = $this->checkThresholds();
        
        return [
            'active' => $isActive,
            'violations' => $violations,
            'thresholds' => $this->thresholds,
            'current_metrics' => [
                'memory_usage' => $this->getMemoryUsage(),
                'cpu_usage' => $this->getCpuUsage(),
                'concurrent_executions' => $this->getConcurrentExecutions(),
                'long_running_executions' => $this->getLongRunningExecutions(),
                'failure_rate' => $this->getFailureRate(),
                'error_rate_per_minute' => $this->getErrorRatePerMinute(),
            ],
            'last_check' => now()->toISOString(),
        ];
    }
}