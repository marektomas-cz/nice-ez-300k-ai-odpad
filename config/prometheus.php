<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Prometheus Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for Prometheus metrics
    | collection and monitoring within the scripting solution.
    |
    */

    'enabled' => env('PROMETHEUS_ENABLED', true),

    'namespace' => env('PROMETHEUS_NAMESPACE', 'nice_scripting'),

    'metrics_path' => env('PROMETHEUS_METRICS_PATH', '/metrics'),

    'redis_key' => env('PROMETHEUS_REDIS_KEY', 'prometheus_metrics'),

    'redis_prefix' => env('PROMETHEUS_REDIS_PREFIX', 'nice_scripting_metrics:'),

    'collect_default_metrics' => env('PROMETHEUS_COLLECT_DEFAULT_METRICS', true),

    'metrics' => [
        'script_executions_total' => [
            'type' => 'counter',
            'help' => 'Total number of script executions',
            'labels' => ['script_id', 'client_id', 'status'],
        ],
        'script_execution_duration_seconds' => [
            'type' => 'histogram',
            'help' => 'Script execution duration in seconds',
            'labels' => ['script_id', 'client_id'],
            'buckets' => [0.1, 0.5, 1.0, 2.5, 5.0, 10.0, 30.0, 60.0, 120.0],
        ],
        'script_memory_usage_bytes' => [
            'type' => 'histogram',
            'help' => 'Script memory usage in bytes',
            'labels' => ['script_id', 'client_id'],
            'buckets' => [1024, 10240, 102400, 1048576, 10485760, 104857600, 536870912],
        ],
        'script_errors_total' => [
            'type' => 'counter',
            'help' => 'Total number of script errors',
            'labels' => ['script_id', 'client_id', 'error_type'],
        ],
        'script_security_violations_total' => [
            'type' => 'counter',
            'help' => 'Total number of security violations',
            'labels' => ['script_id', 'client_id', 'violation_type'],
        ],
        'active_script_executions' => [
            'type' => 'gauge',
            'help' => 'Number of currently active script executions',
            'labels' => ['client_id'],
        ],
        'script_queue_size' => [
            'type' => 'gauge',
            'help' => 'Number of scripts in execution queue',
            'labels' => ['queue_name'],
        ],
        'script_database_queries_total' => [
            'type' => 'counter',
            'help' => 'Total number of database queries from scripts',
            'labels' => ['script_id', 'client_id', 'query_type'],
        ],
        'script_http_requests_total' => [
            'type' => 'counter',
            'help' => 'Total number of HTTP requests from scripts',
            'labels' => ['script_id', 'client_id', 'method', 'status_code'],
        ],
        'script_compilation_duration_seconds' => [
            'type' => 'histogram',
            'help' => 'Script compilation duration in seconds',
            'labels' => ['script_id'],
            'buckets' => [0.01, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0],
        ],
        'script_versions_total' => [
            'type' => 'counter',
            'help' => 'Total number of script versions created',
            'labels' => ['script_id', 'client_id'],
        ],
    ],

    'alerts' => [
        'high_error_rate' => [
            'enabled' => true,
            'threshold' => 0.1, // 10% error rate
            'window' => 300, // 5 minutes
        ],
        'long_execution_time' => [
            'enabled' => true,
            'threshold' => 30, // 30 seconds
        ],
        'high_memory_usage' => [
            'enabled' => true,
            'threshold' => 536870912, // 512MB
        ],
        'security_violations' => [
            'enabled' => true,
            'threshold' => 1, // Any security violation
        ],
        'queue_backlog' => [
            'enabled' => true,
            'threshold' => 100, // 100 items in queue
        ],
    ],

    'retention' => [
        'enabled' => true,
        'days' => 30,
        'cleanup_interval' => 3600, // 1 hour
    ],

    'exporters' => [
        'redis' => [
            'enabled' => true,
            'connection' => env('REDIS_CONNECTION', 'default'),
        ],
        'file' => [
            'enabled' => false,
            'path' => storage_path('metrics/prometheus.txt'),
        ],
    ],
];