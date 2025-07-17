<?php

namespace App\Services\Scripting;

use App\Models\ScriptExecutionLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ResourceMonitorService
{
    protected array $activeMonitors = [];
    protected array $resourceLimits;

    public function __construct()
    {
        $this->resourceLimits = [
            'memory_limit' => config('scripting.execution.memory_limit', 32) * 1024 * 1024, // bytes
            'time_limit' => config('scripting.execution.timeout', 30), // seconds
            'max_concurrent' => config('scripting.execution.max_concurrent_executions', 10),
        ];
    }

    /**
     * Start monitoring resources for a script execution
     */
    public function startMonitoring(int $executionLogId): void
    {
        $this->activeMonitors[$executionLogId] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_usage(true),
            'cpu_start' => $this->getCurrentCpuUsage(),
        ];

        // Store in cache for persistence across requests
        Cache::put("script_monitor_{$executionLogId}", $this->activeMonitors[$executionLogId], 3600);
    }

    /**
     * Stop monitoring and return final resource usage
     */
    public function stopMonitoring(int $executionLogId): array
    {
        $monitor = $this->activeMonitors[$executionLogId] ?? Cache::get("script_monitor_{$executionLogId}");
        
        if (!$monitor) {
            return $this->getDefaultResourceUsage();
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $resourceUsage = [
            'execution_time' => $endTime - $monitor['start_time'],
            'memory_used' => $endMemory - $monitor['start_memory'],
            'peak_memory' => max($peakMemory, $monitor['peak_memory']),
            'cpu_usage' => $this->getCurrentCpuUsage() - $monitor['cpu_start'],
            'memory_limit' => $this->resourceLimits['memory_limit'],
            'time_limit' => $this->resourceLimits['time_limit'],
        ];

        // Clean up
        unset($this->activeMonitors[$executionLogId]);
        Cache::forget("script_monitor_{$executionLogId}");

        return $resourceUsage;
    }

    /**
     * Get current resource usage for an execution
     */
    public function getResourceUsage(int $executionLogId): array
    {
        $monitor = $this->activeMonitors[$executionLogId] ?? Cache::get("script_monitor_{$executionLogId}");
        
        if (!$monitor) {
            return $this->getDefaultResourceUsage();
        }

        $currentTime = microtime(true);
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        return [
            'execution_time' => $currentTime - $monitor['start_time'],
            'memory_used' => $currentMemory - $monitor['start_memory'],
            'peak_memory' => max($peakMemory, $monitor['peak_memory']),
            'cpu_usage' => $this->getCurrentCpuUsage() - $monitor['cpu_start'],
            'memory_limit' => $this->resourceLimits['memory_limit'],
            'time_limit' => $this->resourceLimits['time_limit'],
        ];
    }

    /**
     * Check if system has available resources for execution
     */
    public function hasAvailableResources(): bool
    {
        // Check concurrent executions
        $activeCount = count($this->activeMonitors) + Cache::get('active_script_count', 0);
        if ($activeCount >= $this->resourceLimits['max_concurrent']) {
            return false;
        }

        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit !== '-1') {
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            if ($memoryUsage > $memoryLimitBytes * 0.8) { // 80% threshold
                return false;
            }
        }

        // Check system load (if available)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load[0] > 10) { // High load threshold
                return false;
            }
        }

        return true;
    }

    /**
     * Get resource metrics for monitoring
     */
    public function getSystemMetrics(): array
    {
        return [
            'active_executions' => count($this->activeMonitors),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'memory_limit' => $this->convertToBytes(ini_get('memory_limit')),
            'system_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
            'cpu_usage' => $this->getCurrentCpuUsage(),
        ];
    }

    /**
     * Check if execution has exceeded resource limits
     */
    public function hasExceededLimits(int $executionLogId): array
    {
        $usage = $this->getResourceUsage($executionLogId);
        $violations = [];

        if ($usage['execution_time'] > $this->resourceLimits['time_limit']) {
            $violations[] = 'time_limit';
        }

        if ($usage['peak_memory'] > $this->resourceLimits['memory_limit']) {
            $violations[] = 'memory_limit';
        }

        return $violations;
    }

    /**
     * Get current CPU usage (simplified)
     */
    protected function getCurrentCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] ?? 0.0;
        }

        // Fallback to process time
        $rusage = getrusage();
        return ($rusage['ru_utime.tv_sec'] + $rusage['ru_utime.tv_usec'] / 1000000) +
               ($rusage['ru_stime.tv_sec'] + $rusage['ru_stime.tv_usec'] / 1000000);
    }

    /**
     * Convert memory string to bytes
     */
    protected function convertToBytes(string $value): int
    {
        if ($value === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($value, -1));
        $value = (int) $value;

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Get default resource usage structure
     */
    protected function getDefaultResourceUsage(): array
    {
        return [
            'execution_time' => 0,
            'memory_used' => 0,
            'peak_memory' => 0,
            'cpu_usage' => 0,
            'memory_limit' => $this->resourceLimits['memory_limit'],
            'time_limit' => $this->resourceLimits['time_limit'],
        ];
    }

    /**
     * Clean up old monitoring data
     */
    public function cleanup(): void
    {
        $cutoff = time() - 3600; // 1 hour ago
        
        foreach (Cache::get('script_monitors', []) as $executionLogId => $startTime) {
            if ($startTime < $cutoff) {
                Cache::forget("script_monitor_{$executionLogId}");
            }
        }
    }
}