<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Storage\Redis;

class PrometheusMetricsService
{
    protected CollectorRegistry $registry;
    protected array $counters = [];
    protected array $gauges = [];
    protected array $histograms = [];

    public function __construct()
    {
        $this->initializeRegistry();
        $this->initializeMetrics();
    }

    /**
     * Initialize Prometheus registry
     */
    protected function initializeRegistry(): void
    {
        $redisConfig = config('database.redis.default');
        
        $redisAdapter = new Redis([
            'host' => $redisConfig['host'],
            'port' => $redisConfig['port'],
            'database' => $redisConfig['database'] ?? 0,
            'password' => $redisConfig['password'] ?? null,
        ]);

        $this->registry = new CollectorRegistry($redisAdapter);
    }

    /**
     * Initialize all metrics
     */
    protected function initializeMetrics(): void
    {
        $this->initializeCounters();
        $this->initializeGauges();
        $this->initializeHistograms();
    }

    /**
     * Initialize counter metrics
     */
    protected function initializeCounters(): void
    {
        $this->counters = [
            'script_executions_total' => $this->registry->registerCounter(
                'script_executions_total',
                'Total number of script executions',
                ['client_id', 'script_id', 'status', 'trigger_type']
            ),
            'security_violations_total' => $this->registry->registerCounter(
                'security_violations_total',
                'Total number of security violations',
                ['client_id', 'script_id', 'violation_type']
            ),
            'kill_switch_triggers_total' => $this->registry->registerCounter(
                'kill_switch_triggers_total',
                'Total number of kill switch triggers',
                ['trigger_reason']
            ),
            'ast_analysis_total' => $this->registry->registerCounter(
                'ast_analysis_total',
                'Total number of AST security analyses',
                ['client_id', 'risk_level']
            ),
            'deno_executor_requests_total' => $this->registry->registerCounter(
                'deno_executor_requests_total',
                'Total number of requests to Deno executor',
                ['status']
            ),
            'api_requests_total' => $this->registry->registerCounter(
                'api_requests_total',
                'Total number of API requests',
                ['method', 'endpoint', 'status']
            ),
        ];
    }

    /**
     * Initialize gauge metrics
     */
    protected function initializeGauges(): void
    {
        $this->gauges = [
            'concurrent_executions' => $this->registry->registerGauge(
                'concurrent_executions',
                'Number of currently running script executions',
                ['client_id']
            ),
            'system_memory_usage_percent' => $this->registry->registerGauge(
                'system_memory_usage_percent',
                'System memory usage percentage',
                []
            ),
            'system_cpu_usage_percent' => $this->registry->registerGauge(
                'system_cpu_usage_percent',
                'System CPU usage percentage',
                []
            ),
            'kill_switch_active' => $this->registry->registerGauge(
                'kill_switch_active',
                'Whether kill switch is currently active (1 = active, 0 = inactive)',
                []
            ),
            'deno_executor_health' => $this->registry->registerGauge(
                'deno_executor_health',
                'Health status of Deno executor (1 = healthy, 0 = unhealthy)',
                []
            ),
            'script_security_score' => $this->registry->registerGauge(
                'script_security_score',
                'Security score of analyzed scripts',
                ['client_id', 'script_id']
            ),
            'active_users' => $this->registry->registerGauge(
                'active_users',
                'Number of active users in the system',
                []
            ),
        ];
    }

    /**
     * Initialize histogram metrics
     */
    protected function initializeHistograms(): void
    {
        $this->histograms = [
            'script_execution_duration_seconds' => $this->registry->registerHistogram(
                'script_execution_duration_seconds',
                'Script execution duration in seconds',
                ['client_id', 'script_id', 'status'],
                [0.1, 0.5, 1.0, 2.0, 5.0, 10.0, 30.0, 60.0, 120.0]
            ),
            'ast_analysis_duration_seconds' => $this->registry->registerHistogram(
                'ast_analysis_duration_seconds',
                'AST security analysis duration in seconds',
                ['client_id'],
                [0.01, 0.05, 0.1, 0.5, 1.0, 2.0, 5.0]
            ),
            'deno_executor_response_time_seconds' => $this->registry->registerHistogram(
                'deno_executor_response_time_seconds',
                'Deno executor response time in seconds',
                ['endpoint'],
                [0.01, 0.05, 0.1, 0.5, 1.0, 2.0, 5.0, 10.0]
            ),
            'api_request_duration_seconds' => $this->registry->registerHistogram(
                'api_request_duration_seconds',
                'API request duration in seconds',
                ['method', 'endpoint'],
                [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
            ),
        ];
    }

    /**
     * Increment script execution counter
     */
    public function incrementScriptExecution(string $clientId, string $scriptId, string $status, string $triggerType): void
    {
        $this->counters['script_executions_total']->inc([$clientId, $scriptId, $status, $triggerType]);
    }

    /**
     * Record script execution duration
     */
    public function recordScriptExecutionDuration(string $clientId, string $scriptId, string $status, float $duration): void
    {
        $this->histograms['script_execution_duration_seconds']->observe($duration, [$clientId, $scriptId, $status]);
    }

    /**
     * Update concurrent executions gauge
     */
    public function setConcurrentExecutions(string $clientId, int $count): void
    {
        $this->gauges['concurrent_executions']->set($count, [$clientId]);
    }

    /**
     * Increment security violations counter
     */
    public function incrementSecurityViolation(string $clientId, string $scriptId, string $violationType): void
    {
        $this->counters['security_violations_total']->inc([$clientId, $scriptId, $violationType]);
    }

    /**
     * Increment kill switch triggers counter
     */
    public function incrementKillSwitchTriggers(string $triggerReason = 'threshold_violation'): void
    {
        $this->counters['kill_switch_triggers_total']->inc([$triggerReason]);
    }

    /**
     * Set kill switch active status
     */
    public function setKillSwitchActive(bool $active): void
    {
        $this->gauges['kill_switch_active']->set($active ? 1 : 0, []);
    }

    /**
     * Update system memory usage
     */
    public function setSystemMemoryUsage(float $percentage): void
    {
        $this->gauges['system_memory_usage_percent']->set($percentage, []);
    }

    /**
     * Update system CPU usage
     */
    public function setSystemCpuUsage(float $percentage): void
    {
        $this->gauges['system_cpu_usage_percent']->set($percentage, []);
    }

    /**
     * Increment AST analysis counter
     */
    public function incrementAstAnalysis(string $clientId, string $riskLevel): void
    {
        $this->counters['ast_analysis_total']->inc([$clientId, $riskLevel]);
    }

    /**
     * Record AST analysis duration
     */
    public function recordAstAnalysisDuration(string $clientId, float $duration): void
    {
        $this->histograms['ast_analysis_duration_seconds']->observe($duration, [$clientId]);
    }

    /**
     * Set script security score
     */
    public function setScriptSecurityScore(string $clientId, string $scriptId, float $score): void
    {
        $this->gauges['script_security_score']->set($score, [$clientId, $scriptId]);
    }

    /**
     * Increment Deno executor requests counter
     */
    public function incrementDenoExecutorRequest(string $status): void
    {
        $this->counters['deno_executor_requests_total']->inc([$status]);
    }

    /**
     * Record Deno executor response time
     */
    public function recordDenoExecutorResponseTime(string $endpoint, float $duration): void
    {
        $this->histograms['deno_executor_response_time_seconds']->observe($duration, [$endpoint]);
    }

    /**
     * Set Deno executor health status
     */
    public function setDenoExecutorHealth(bool $healthy): void
    {
        $this->gauges['deno_executor_health']->set($healthy ? 1 : 0, []);
    }

    /**
     * Increment API requests counter
     */
    public function incrementApiRequest(string $method, string $endpoint, int $status): void
    {
        $statusGroup = floor($status / 100) . 'xx';
        $this->counters['api_requests_total']->inc([$method, $endpoint, $statusGroup]);
    }

    /**
     * Record API request duration
     */
    public function recordApiRequestDuration(string $method, string $endpoint, float $duration): void
    {
        $this->histograms['api_request_duration_seconds']->observe($duration, [$method, $endpoint]);
    }

    /**
     * Set number of active users
     */
    public function setActiveUsers(int $count): void
    {
        $this->gauges['active_users']->set($count, []);
    }

    /**
     * Get metrics for Prometheus scraping
     */
    public function getMetrics(): string
    {
        $renderer = new \Prometheus\RenderTextFormat();
        return $renderer->render($this->registry->getMetricFamilySamples());
    }

    /**
     * Update all system metrics
     */
    public function updateSystemMetrics(): void
    {
        try {
            // Update memory usage
            $memoryUsage = $this->getMemoryUsage();
            $this->setSystemMemoryUsage($memoryUsage);

            // Update CPU usage
            $cpuUsage = $this->getCpuUsage();
            $this->setSystemCpuUsage($cpuUsage);

            // Update kill switch status
            $killSwitchActive = Cache::get('kill_switch_active', false);
            $this->setKillSwitchActive($killSwitchActive);

            // Update Deno executor health
            $denoHealth = $this->checkDenoExecutorHealth();
            $this->setDenoExecutorHealth($denoHealth);

            // Update active users count
            $activeUsers = $this->getActiveUsersCount();
            $this->setActiveUsers($activeUsers);

        } catch (\Exception $e) {
            Log::error('Failed to update system metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get memory usage percentage
     */
    protected function getMemoryUsage(): float
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $memInfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
            preg_match('/MemFree:\s+(\d+)/', $memInfo, $free);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $available);
            
            $totalMem = (int)($total[1] ?? 0);
            $freeMem = (int)($free[1] ?? 0);
            
            if ($totalMem > 0) {
                return (($totalMem - $freeMem) / $totalMem) * 100;
            }
        }
        
        return 0;
    }

    /**
     * Get CPU usage percentage
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
     * Check Deno executor health
     */
    protected function checkDenoExecutorHealth(): bool
    {
        try {
            $denoServiceUrl = config('scripting.deno.service_url', 'http://deno-executor:8080');
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($denoServiceUrl . '/health');
            
            return $response->successful() && $response->body() === 'OK';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get active users count
     */
    protected function getActiveUsersCount(): int
    {
        // This would depend on your user tracking implementation
        // For now, return a placeholder
        return \App\Models\User::where('last_seen_at', '>=', now()->subMinutes(15))->count();
    }

    /**
     * Clear all metrics
     */
    public function clearMetrics(): void
    {
        $this->registry->wipeStorage();
    }

    /**
     * Get specific metric value
     */
    public function getMetricValue(string $metricName, array $labels = []): ?float
    {
        $samples = $this->registry->getMetricFamilySamples();
        
        foreach ($samples as $sample) {
            if ($sample->getName() === $metricName) {
                foreach ($sample->getSamples() as $metricSample) {
                    if (empty($labels) || $metricSample->getLabelValues() === array_values($labels)) {
                        return $metricSample->getValue();
                    }
                }
            }
        }
        
        return null;
    }
}