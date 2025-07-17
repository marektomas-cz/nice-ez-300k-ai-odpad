<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scripting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the in-app scripting system.
    | These settings control security, performance, and feature availability.
    |
    */

    'enabled' => env('SCRIPTING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Script Execution Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how scripts are executed and what resources
    | they can consume.
    |
    */

    'execution' => [
        'timeout' => env('SCRIPT_TIMEOUT', 30), // seconds
        'memory_limit' => env('SCRIPT_MEMORY_LIMIT', 32), // MB
        'max_output_size' => env('SCRIPT_MAX_OUTPUT_SIZE', 1024 * 1024), // bytes
        'max_concurrent_executions' => env('SCRIPT_MAX_CONCURRENT', 10),
        'queue_driver' => env('SCRIPT_QUEUE_DRIVER', 'sync'),
        'queue_name' => env('SCRIPT_QUEUE_NAME', 'scripting'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | These settings control security features and restrictions.
    |
    */

    'security' => [
        'enable_content_validation' => env('SCRIPT_SECURITY_VALIDATION', true),
        'enable_resource_monitoring' => env('SCRIPT_RESOURCE_MONITORING', true),
        'enable_rate_limiting' => env('SCRIPT_RATE_LIMITING', true),
        'enable_audit_logging' => env('SCRIPT_AUDIT_LOGGING', true),
        'max_script_size' => env('SCRIPT_MAX_SIZE', 65535), // bytes
        'max_nesting_depth' => env('SCRIPT_MAX_NESTING', 10),
        'forbidden_functions' => [
            'eval',
            'Function',
            'setTimeout',
            'setInterval',
            'XMLHttpRequest',
            'fetch',
            'import',
            'require',
        ],
        'allowed_globals' => [
            'JSON',
            'Math',
            'Date',
            'String',
            'Number',
            'Boolean',
            'Array',
            'Object',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Access Settings
    |--------------------------------------------------------------------------
    |
    | These settings control database access from scripts.
    |
    */

    'database' => [
        'enable_database_access' => env('SCRIPT_DATABASE_ACCESS', true),
        'allowed_tables' => [
            'users',
            'orders',
            'products',
            'categories',
            'customers',
            // Add client-specific tables as needed
        ],
        'forbidden_tables' => [
            'scripts',
            'script_execution_logs',
            'clients',
            'migrations',
            'password_resets',
            'failed_jobs',
            'sessions',
        ],
        'max_query_results' => env('SCRIPT_MAX_QUERY_RESULTS', 1000),
        'enable_write_operations' => env('SCRIPT_ENABLE_WRITES', false),
        'allowed_operations' => [
            'select',
            'insert',
            'update',
            'delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Settings
    |--------------------------------------------------------------------------
    |
    | These settings control external HTTP requests from scripts.
    |
    */

    'http' => [
        'enable_http_access' => env('SCRIPT_HTTP_ACCESS', true),
        'allowed_domains' => [
            'api.example.com',
            'webhook.example.com',
            '*.trusted-domain.com',
        ],
        'forbidden_domains' => [
            'localhost',
            '127.0.0.1',
            '*.internal',
            '*.local',
        ],
        'allowed_methods' => [
            'GET',
            'POST',
            'PUT',
            'DELETE',
            'PATCH',
        ],
        'max_request_size' => env('SCRIPT_MAX_REQUEST_SIZE', 1024 * 1024), // bytes
        'max_response_size' => env('SCRIPT_MAX_RESPONSE_SIZE', 1024 * 1024), // bytes
        'request_timeout' => env('SCRIPT_HTTP_TIMEOUT', 10), // seconds
        'max_redirects' => env('SCRIPT_MAX_REDIRECTS', 3),
        'user_agent' => env('SCRIPT_USER_AGENT', 'ScriptingEngine/1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event System Settings
    |--------------------------------------------------------------------------
    |
    | These settings control event dispatching from scripts.
    |
    */

    'events' => [
        'enable_event_dispatch' => env('SCRIPT_EVENTS_ENABLED', true),
        'allowed_events' => [
            'script.custom.*',
            'user.notification',
            'order.status.changed',
            'product.updated',
        ],
        'forbidden_events' => [
            'script.*',
            'system.*',
            'auth.*',
            'security.*',
        ],
        'max_event_payload_size' => env('SCRIPT_MAX_EVENT_PAYLOAD', 8192), // bytes
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | These settings control logging from scripts.
    |
    */

    'logging' => [
        'enable_script_logging' => env('SCRIPT_LOGGING_ENABLED', true),
        'log_channel' => env('SCRIPT_LOG_CHANNEL', 'scripting'),
        'max_log_message_size' => env('SCRIPT_MAX_LOG_MESSAGE', 1024), // bytes
        'max_logs_per_execution' => env('SCRIPT_MAX_LOGS_PER_EXECUTION', 100),
        'allowed_log_levels' => [
            'debug',
            'info',
            'warning',
            'error',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Settings
    |--------------------------------------------------------------------------
    |
    | These settings control rate limiting for script executions.
    |
    */

    'rate_limiting' => [
        'enable_rate_limiting' => env('SCRIPT_RATE_LIMITING', true),
        'default_rate_limit' => env('SCRIPT_DEFAULT_RATE_LIMIT', 100), // per minute
        'burst_limit' => env('SCRIPT_BURST_LIMIT', 10), // concurrent executions
        'cooldown_period' => env('SCRIPT_COOLDOWN_PERIOD', 300), // seconds
        'violation_threshold' => env('SCRIPT_VIOLATION_THRESHOLD', 5),
        'violation_ban_duration' => env('SCRIPT_VIOLATION_BAN', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | These settings control caching for script execution and metadata.
    |
    */

    'cache' => [
        'enable_caching' => env('SCRIPT_CACHING_ENABLED', true),
        'cache_driver' => env('SCRIPT_CACHE_DRIVER', 'redis'),
        'cache_prefix' => env('SCRIPT_CACHE_PREFIX', 'scripting:'),
        'syntax_cache_ttl' => env('SCRIPT_SYNTAX_CACHE_TTL', 3600), // seconds
        'stats_cache_ttl' => env('SCRIPT_STATS_CACHE_TTL', 300), // seconds
        'security_cache_ttl' => env('SCRIPT_SECURITY_CACHE_TTL', 1800), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Settings
    |--------------------------------------------------------------------------
    |
    | These settings control monitoring and alerting for the scripting system.
    |
    */

    'monitoring' => [
        'enable_monitoring' => env('SCRIPT_MONITORING_ENABLED', true),
        'metrics_driver' => env('SCRIPT_METRICS_DRIVER', 'log'),
        'alert_thresholds' => [
            'error_rate' => env('SCRIPT_ERROR_RATE_THRESHOLD', 0.1), // 10%
            'avg_execution_time' => env('SCRIPT_AVG_TIME_THRESHOLD', 5.0), // seconds
            'memory_usage' => env('SCRIPT_MEMORY_THRESHOLD', 0.8), // 80%
            'concurrent_executions' => env('SCRIPT_CONCURRENT_THRESHOLD', 8),
        ],
        'alert_channels' => [
            'email' => env('SCRIPT_ALERT_EMAIL', 'admin@example.com'),
            'slack' => env('SCRIPT_ALERT_SLACK', null),
            'webhook' => env('SCRIPT_ALERT_WEBHOOK', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup and Recovery Settings
    |--------------------------------------------------------------------------
    |
    | These settings control backup and recovery features.
    |
    */

    'backup' => [
        'enable_backup' => env('SCRIPT_BACKUP_ENABLED', true),
        'backup_driver' => env('SCRIPT_BACKUP_DRIVER', 'local'),
        'backup_schedule' => env('SCRIPT_BACKUP_SCHEDULE', 'daily'),
        'retention_days' => env('SCRIPT_BACKUP_RETENTION', 30),
        'compress_backups' => env('SCRIPT_BACKUP_COMPRESS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Settings
    |--------------------------------------------------------------------------
    |
    | These settings are for development and testing purposes.
    |
    */

    'development' => [
        'enable_debug_mode' => env('SCRIPT_DEBUG_MODE', false),
        'enable_profiling' => env('SCRIPT_PROFILING', false),
        'mock_external_requests' => env('SCRIPT_MOCK_REQUESTS', false),
        'test_mode' => env('SCRIPT_TEST_MODE', false),
        'log_all_executions' => env('SCRIPT_LOG_ALL_EXECUTIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | These settings control feature availability.
    |
    */

    'features' => [
        'enable_script_editor' => env('SCRIPT_EDITOR_ENABLED', true),
        'enable_syntax_highlighting' => env('SCRIPT_SYNTAX_HIGHLIGHTING', true),
        'enable_auto_completion' => env('SCRIPT_AUTO_COMPLETION', true),
        'enable_script_templates' => env('SCRIPT_TEMPLATES_ENABLED', true),
        'enable_script_sharing' => env('SCRIPT_SHARING_ENABLED', false),
        'enable_script_marketplace' => env('SCRIPT_MARKETPLACE_ENABLED', false),
        'enable_version_control' => env('SCRIPT_VERSION_CONTROL', true),
        'enable_collaboration' => env('SCRIPT_COLLABORATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | These settings control API access to the scripting system.
    |
    */

    'api' => [
        'enable_api_access' => env('SCRIPT_API_ENABLED', true),
        'api_rate_limit' => env('SCRIPT_API_RATE_LIMIT', 60), // per minute
        'require_api_key' => env('SCRIPT_API_KEY_REQUIRED', true),
        'api_version' => env('SCRIPT_API_VERSION', 'v1'),
        'enable_webhooks' => env('SCRIPT_WEBHOOKS_ENABLED', true),
        'webhook_secret' => env('SCRIPT_WEBHOOK_SECRET', null),
        'webhook_timeout' => env('SCRIPT_WEBHOOK_TIMEOUT', 10), // seconds
    ],

];