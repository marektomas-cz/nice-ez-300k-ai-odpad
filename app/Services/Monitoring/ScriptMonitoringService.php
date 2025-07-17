<?php

namespace App\Services\Monitoring;

use App\Models\Script;
use App\Models\ScriptExecutionLog;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ScriptMonitoringService
{
    /**
     * Get comprehensive dashboard metrics
     */
    public function getDashboardMetrics(?int $clientId = null): array
    {
        $cacheKey = "dashboard_metrics_" . ($clientId ?? 'global');
        
        return Cache::remember($cacheKey, 300, function () use ($clientId) {
            $baseQuery = $clientId ? 
                ScriptExecutionLog::where('client_id', $clientId) : 
                ScriptExecutionLog::query();

            $scriptsQuery = $clientId ? 
                Script::where('client_id', $clientId) : 
                Script::query();

            return [
                'overview' => $this->getOverviewMetrics($baseQuery, $scriptsQuery),
                'performance' => $this->getPerformanceMetrics($baseQuery),
                'security' => $this->getSecurityMetrics($baseQuery),
                'trends' => $this->getTrendMetrics($baseQuery),
                'alerts' => $this->getActiveAlerts($clientId),
            ];
        });
    }

    /**
     * Get overview metrics
     */
    protected function getOverviewMetrics($executionQuery, $scriptsQuery): array
    {
        $totalExecutions = $executionQuery->count();
        $successfulExecutions = $executionQuery->where('status', 'success')->count();
        $failedExecutions = $executionQuery->where('status', 'failed')->count();
        $runningExecutions = $executionQuery->where('status', 'running')->count();

        return [
            'total_scripts' => $scriptsQuery->count(),
            'active_scripts' => $scriptsQuery->where('is_active', true)->count(),
            'total_executions' => $totalExecutions,
            'successful_executions' => $successfulExecutions,
            'failed_executions' => $failedExecutions,
            'running_executions' => $runningExecutions,
            'success_rate' => $totalExecutions > 0 ? ($successfulExecutions / $totalExecutions) * 100 : 0,
            'failure_rate' => $totalExecutions > 0 ? ($failedExecutions / $totalExecutions) * 100 : 0,
            'avg_execution_time' => $executionQuery->where('status', 'success')->avg('execution_time') ?? 0,
            'total_memory_usage' => $executionQuery->sum('memory_usage') ?? 0,
        ];
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics($executionQuery): array
    {
        $successfulExecutions = $executionQuery->where('status', 'success');
        
        return [
            'avg_execution_time' => $successfulExecutions->avg('execution_time') ?? 0,
            'min_execution_time' => $successfulExecutions->min('execution_time') ?? 0,
            'max_execution_time' => $successfulExecutions->max('execution_time') ?? 0,
            'p95_execution_time' => $this->getPercentile($successfulExecutions, 'execution_time', 95),
            'p99_execution_time' => $this->getPercentile($successfulExecutions, 'execution_time', 99),
            'avg_memory_usage' => $successfulExecutions->avg('memory_usage') ?? 0,
            'max_memory_usage' => $successfulExecutions->max('memory_usage') ?? 0,
            'concurrent_executions' => $this->getConcurrentExecutions(),
            'throughput' => $this->getThroughput($executionQuery),
        ];
    }

    /**
     * Get security metrics
     */
    protected function getSecurityMetrics($executionQuery): array
    {
        $securityFlags = $executionQuery->whereNotNull('security_flags')
            ->whereRaw('JSON_LENGTH(security_flags) > 0')
            ->get()
            ->pluck('security_flags')
            ->flatten(1);

        $flagTypes = $securityFlags->groupBy('type')->map->count();

        return [
            'total_security_events' => $securityFlags->count(),
            'security_violations' => $flagTypes->get('security_violation', 0),
            'rate_limit_violations' => $flagTypes->get('rate_limit', 0),
            'content_violations' => $flagTypes->get('content_validation', 0),
            'blocked_executions' => $executionQuery->where('status', 'failed')
                ->whereRaw('JSON_EXTRACT(security_flags, "$[0].type") IS NOT NULL')
                ->count(),
            'avg_security_score' => $this->getAverageSecurityScore(),
        ];
    }

    /**
     * Get trend metrics
     */
    protected function getTrendMetrics($executionQuery): array
    {
        $last24Hours = $executionQuery->where('created_at', '>=', now()->subHours(24));
        $last7Days = $executionQuery->where('created_at', '>=', now()->subDays(7));
        
        return [
            'executions_last_24h' => $last24Hours->count(),
            'executions_last_7d' => $last7Days->count(),
            'hourly_trend' => $this->getHourlyTrend($executionQuery),
            'daily_trend' => $this->getDailyTrend($executionQuery),
            'success_rate_trend' => $this->getSuccessRateTrend($executionQuery),
            'performance_trend' => $this->getPerformanceTrend($executionQuery),
        ];
    }

    /**
     * Get active alerts
     */
    protected function getActiveAlerts(?int $clientId = null): array
    {
        $alerts = [];
        $thresholds = config('scripting.monitoring.alert_thresholds');

        // Check error rate
        $errorRate = $this->getErrorRate($clientId);
        if ($errorRate > $thresholds['error_rate']) {
            $alerts[] = [
                'type' => 'error_rate',
                'level' => 'high',
                'message' => "Error rate ({$errorRate}%) exceeds threshold ({$thresholds['error_rate']}%)",
                'value' => $errorRate,
                'threshold' => $thresholds['error_rate'],
                'timestamp' => now()->toISOString(),
            ];
        }

        // Check average execution time
        $avgTime = $this->getAverageExecutionTime($clientId);
        if ($avgTime > $thresholds['avg_execution_time']) {
            $alerts[] = [
                'type' => 'execution_time',
                'level' => 'medium',
                'message' => "Average execution time ({$avgTime}s) exceeds threshold ({$thresholds['avg_execution_time']}s)",
                'value' => $avgTime,
                'threshold' => $thresholds['avg_execution_time'],
                'timestamp' => now()->toISOString(),
            ];
        }

        // Check concurrent executions
        $concurrentExecutions = $this->getConcurrentExecutions($clientId);
        if ($concurrentExecutions > $thresholds['concurrent_executions']) {
            $alerts[] = [
                'type' => 'concurrent_executions',
                'level' => 'high',
                'message' => "Concurrent executions ({$concurrentExecutions}) exceeds threshold ({$thresholds['concurrent_executions']})",
                'value' => $concurrentExecutions,
                'threshold' => $thresholds['concurrent_executions'],
                'timestamp' => now()->toISOString(),
            ];
        }

        return $alerts;
    }

    /**
     * Get script performance analysis
     */
    public function getScriptPerformanceAnalysis(Script $script): array
    {
        $executions = $script->executionLogs()
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($executions->isEmpty()) {
            return [
                'summary' => ['no_data' => true],
                'trends' => [],
                'recommendations' => [],
            ];
        }

        $successful = $executions->where('status', 'success');
        $failed = $executions->where('status', 'failed');

        return [
            'summary' => [
                'total_executions' => $executions->count(),
                'successful_executions' => $successful->count(),
                'failed_executions' => $failed->count(),
                'success_rate' => $executions->count() > 0 ? ($successful->count() / $executions->count()) * 100 : 0,
                'avg_execution_time' => $successful->avg('execution_time') ?? 0,
                'avg_memory_usage' => $successful->avg('memory_usage') ?? 0,
                'last_execution' => $executions->first()?->created_at,
                'longest_execution' => $successful->max('execution_time') ?? 0,
                'shortest_execution' => $successful->min('execution_time') ?? 0,
            ],
            'trends' => [
                'execution_time_trend' => $this->getExecutionTimeTrend($executions),
                'memory_usage_trend' => $this->getMemoryUsageTrend($executions),
                'error_trend' => $this->getErrorTrend($executions),
            ],
            'recommendations' => $this->getPerformanceRecommendations($script, $executions),
        ];
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealthMetrics(): array
    {
        return [
            'database' => $this->getDatabaseHealth(),
            'queue' => $this->getQueueHealth(),
            'cache' => $this->getCacheHealth(),
            'storage' => $this->getStorageHealth(),
            'memory' => $this->getMemoryHealth(),
            'response_time' => $this->getSystemResponseTime(),
        ];
    }

    /**
     * Get client-specific metrics
     */
    public function getClientMetrics(Client $client): array
    {
        $executions = $client->executionLogs()
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        return [
            'overview' => [
                'total_scripts' => $client->scripts()->count(),
                'active_scripts' => $client->activeScripts()->count(),
                'total_executions' => $executions->count(),
                'quota_usage' => $this->getQuotaUsage($client),
                'rate_limit_usage' => $this->getRateLimitUsage($client),
            ],
            'performance' => [
                'avg_execution_time' => $executions->where('status', 'success')->avg('execution_time') ?? 0,
                'success_rate' => $executions->count() > 0 ? 
                    ($executions->where('status', 'success')->count() / $executions->count()) * 100 : 0,
                'error_rate' => $executions->count() > 0 ? 
                    ($executions->where('status', 'failed')->count() / $executions->count()) * 100 : 0,
            ],
            'security' => [
                'security_events' => $executions->whereNotNull('security_flags')->count(),
                'violations' => $executions->where('status', 'failed')
                    ->whereRaw('JSON_EXTRACT(security_flags, "$[0].type") IS NOT NULL')
                    ->count(),
            ],
            'trends' => $this->getClientTrends($client),
        ];
    }

    /**
     * Generate monitoring report
     */
    public function generateMonitoringReport(array $options = []): array
    {
        $period = $options['period'] ?? 'last_7_days';
        $clientId = $options['client_id'] ?? null;
        $includeDetails = $options['include_details'] ?? true;

        $timeRange = $this->getTimeRange($period);
        
        $report = [
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'period' => $period,
                'client_id' => $clientId,
                'time_range' => $timeRange,
            ],
            'summary' => $this->getDashboardMetrics($clientId),
            'top_scripts' => $this->getTopScripts($clientId, $timeRange),
            'error_analysis' => $this->getErrorAnalysis($clientId, $timeRange),
            'performance_analysis' => $this->getPerformanceAnalysis($clientId, $timeRange),
        ];

        if ($includeDetails) {
            $report['detailed_metrics'] = $this->getDetailedMetrics($clientId, $timeRange);
            $report['recommendations'] = $this->getSystemRecommendations($clientId);
        }

        return $report;
    }

    /**
     * Helper methods
     */

    protected function getPercentile($query, string $column, int $percentile): float
    {
        $count = $query->count();
        if ($count === 0) return 0;

        $index = (int) ceil($count * $percentile / 100) - 1;
        $value = $query->orderBy($column)->skip($index)->first();
        
        return $value ? $value->$column : 0;
    }

    protected function getConcurrentExecutions(?int $clientId = null): int
    {
        $query = ScriptExecutionLog::where('status', 'running');
        
        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return $query->count();
    }

    protected function getThroughput($executionQuery): float
    {
        $executions = $executionQuery->where('created_at', '>=', now()->subHour())->count();
        return $executions / 60; // executions per minute
    }

    protected function getAverageSecurityScore(): float
    {
        // This would calculate based on security reports
        // For now, return a mock value
        return 85.5;
    }

    protected function getHourlyTrend($executionQuery): array
    {
        return $executionQuery->where('created_at', '>=', now()->subHours(24))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();
    }

    protected function getDailyTrend($executionQuery): array
    {
        return $executionQuery->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();
    }

    protected function getSuccessRateTrend($executionQuery): array
    {
        return $executionQuery->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, 
                        SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful,
                        COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                $rate = $item->total > 0 ? ($item->successful / $item->total) * 100 : 0;
                return [$item->date => $rate];
            })
            ->toArray();
    }

    protected function getPerformanceTrend($executionQuery): array
    {
        return $executionQuery->where('created_at', '>=', now()->subDays(7))
            ->where('status', 'success')
            ->selectRaw('DATE(created_at) as date, AVG(execution_time) as avg_time')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('avg_time', 'date')
            ->toArray();
    }

    protected function getErrorRate(?int $clientId = null): float
    {
        $query = ScriptExecutionLog::where('created_at', '>=', now()->subHours(24));
        
        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $total = $query->count();
        $failed = $query->where('status', 'failed')->count();

        return $total > 0 ? ($failed / $total) * 100 : 0;
    }

    protected function getAverageExecutionTime(?int $clientId = null): float
    {
        $query = ScriptExecutionLog::where('created_at', '>=', now()->subHours(24))
            ->where('status', 'success');
        
        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return $query->avg('execution_time') ?? 0;
    }

    protected function getExecutionTimeTrend($executions): array
    {
        return $executions->where('status', 'success')
            ->groupBy(function ($execution) {
                return $execution->created_at->format('Y-m-d');
            })
            ->map(function ($group) {
                return $group->avg('execution_time');
            })
            ->toArray();
    }

    protected function getMemoryUsageTrend($executions): array
    {
        return $executions->where('status', 'success')
            ->groupBy(function ($execution) {
                return $execution->created_at->format('Y-m-d');
            })
            ->map(function ($group) {
                return $group->avg('memory_usage');
            })
            ->toArray();
    }

    protected function getErrorTrend($executions): array
    {
        return $executions->groupBy(function ($execution) {
            return $execution->created_at->format('Y-m-d');
        })
        ->map(function ($group) {
            $total = $group->count();
            $failed = $group->where('status', 'failed')->count();
            return $total > 0 ? ($failed / $total) * 100 : 0;
        })
        ->toArray();
    }

    protected function getPerformanceRecommendations(Script $script, $executions): array
    {
        $recommendations = [];
        
        $avgExecutionTime = $executions->where('status', 'success')->avg('execution_time');
        $errorRate = $executions->where('status', 'failed')->count() / $executions->count();
        
        if ($avgExecutionTime > 5) {
            $recommendations[] = 'Consider optimizing script for better performance - average execution time is high';
        }
        
        if ($errorRate > 0.1) {
            $recommendations[] = 'High error rate detected - review script logic and error handling';
        }
        
        return $recommendations;
    }

    protected function getTimeRange(string $period): array
    {
        switch ($period) {
            case 'last_24_hours':
                return [now()->subHours(24), now()];
            case 'last_7_days':
                return [now()->subDays(7), now()];
            case 'last_30_days':
                return [now()->subDays(30), now()];
            default:
                return [now()->subDays(7), now()];
        }
    }

    protected function getDatabaseHealth(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time' => $responseTime,
                'connections' => DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getQueueHealth(): array
    {
        // Implementation depends on queue driver
        return [
            'status' => 'healthy',
            'pending_jobs' => 0,
            'failed_jobs' => 0,
        ];
    }

    protected function getCacheHealth(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check', 'ok', 5);
            Cache::get('health_check');
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getStorageHealth(): array
    {
        $disk = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $used = $total - $disk;
        
        return [
            'status' => 'healthy',
            'disk_free' => $disk,
            'disk_total' => $total,
            'disk_usage_percent' => ($used / $total) * 100,
        ];
    }

    protected function getMemoryHealth(): array
    {
        return [
            'status' => 'healthy',
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
        ];
    }

    protected function getSystemResponseTime(): float
    {
        $start = microtime(true);
        // Simulate system check
        usleep(1000); // 1ms
        return (microtime(true) - $start) * 1000;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;
        
        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }

    // Additional helper methods would be implemented here...
    protected function getQuotaUsage(Client $client): float
    {
        $monthlyExecutions = $client->executionLogs()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
            
        return $client->api_quota > 0 ? ($monthlyExecutions / $client->api_quota) * 100 : 0;
    }

    protected function getRateLimitUsage(Client $client): float
    {
        $recentExecutions = $client->executionLogs()
            ->where('created_at', '>=', now()->subMinute())
            ->count();
            
        return $client->rate_limit > 0 ? ($recentExecutions / $client->rate_limit) * 100 : 0;
    }

    protected function getClientTrends(Client $client): array
    {
        // Implementation for client-specific trends
        return [];
    }

    protected function getTopScripts(?int $clientId, array $timeRange): array
    {
        // Implementation for top performing scripts
        return [];
    }

    protected function getErrorAnalysis(?int $clientId, array $timeRange): array
    {
        // Implementation for error analysis
        return [];
    }

    protected function getPerformanceAnalysis(?int $clientId, array $timeRange): array
    {
        // Implementation for performance analysis
        return [];
    }

    protected function getDetailedMetrics(?int $clientId, array $timeRange): array
    {
        // Implementation for detailed metrics
        return [];
    }

    protected function getSystemRecommendations(?int $clientId): array
    {
        // Implementation for system recommendations
        return [];
    }
}