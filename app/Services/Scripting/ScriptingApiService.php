<?php

namespace App\Services\Scripting;

use App\Models\Script;
use App\Models\ScriptExecutionLog;
use App\Services\Security\ScriptSecurityService;
use App\Services\Scripting\Api\DatabaseApiService;
use App\Services\Scripting\Api\HttpApiService;
use App\Services\Scripting\Api\EventApiService;
use App\Services\Scripting\Api\LoggingApiService;
use App\Services\Scripting\Api\ValidationApiService;
use App\Exceptions\ApiAccessDeniedException;
use Illuminate\Support\Facades\Log;

class ScriptingApiService
{
    protected ScriptSecurityService $securityService;
    protected DatabaseApiService $databaseApi;
    protected HttpApiService $httpApi;
    protected EventApiService $eventApi;
    protected LoggingApiService $loggingApi;
    protected ValidationApiService $validationApi;

    public function __construct(
        ScriptSecurityService $securityService,
        DatabaseApiService $databaseApi,
        HttpApiService $httpApi,
        EventApiService $eventApi,
        LoggingApiService $loggingApi,
        ValidationApiService $validationApi
    ) {
        $this->securityService = $securityService;
        $this->databaseApi = $databaseApi;
        $this->httpApi = $httpApi;
        $this->eventApi = $eventApi;
        $this->loggingApi = $loggingApi;
        $this->validationApi = $validationApi;
    }

    /**
     * Create a secure API instance for script execution
     */
    public function createSecureApi(Script $script, array $context, ScriptExecutionLog $executionLog): ScriptingApi
    {
        return new ScriptingApi(
            $script,
            $context,
            $executionLog,
            $this->securityService,
            $this->databaseApi,
            $this->httpApi,
            $this->eventApi,
            $this->loggingApi,
            $this->validationApi
        );
    }
}

/**
 * Secure API wrapper exposed to scripts
 */
class ScriptingApi
{
    protected Script $script;
    protected array $context;
    protected ScriptExecutionLog $executionLog;
    protected ScriptSecurityService $securityService;
    protected DatabaseApiService $databaseApi;
    protected HttpApiService $httpApi;
    protected EventApiService $eventApi;
    protected LoggingApiService $loggingApi;
    protected ValidationApiService $validationApi;

    public function __construct(
        Script $script,
        array $context,
        ScriptExecutionLog $executionLog,
        ScriptSecurityService $securityService,
        DatabaseApiService $databaseApi,
        HttpApiService $httpApi,
        EventApiService $eventApi,
        LoggingApiService $loggingApi,
        ValidationApiService $validationApi
    ) {
        $this->script = $script;
        $this->context = $context;
        $this->executionLog = $executionLog;
        $this->securityService = $securityService;
        $this->databaseApi = $databaseApi;
        $this->httpApi = $httpApi;
        $this->eventApi = $eventApi;
        $this->loggingApi = $loggingApi;
        $this->validationApi = $validationApi;
    }

    /**
     * Database API namespace
     */
    public function database(): DatabaseApiNamespace
    {
        return new DatabaseApiNamespace($this->databaseApi, $this->script, $this->executionLog, $this->securityService);
    }

    /**
     * HTTP API namespace
     */
    public function http(): HttpApiNamespace
    {
        return new HttpApiNamespace($this->httpApi, $this->script, $this->executionLog, $this->securityService);
    }

    /**
     * Event API namespace
     */
    public function events(): EventApiNamespace
    {
        return new EventApiNamespace($this->eventApi, $this->script, $this->executionLog, $this->securityService);
    }

    /**
     * Logging API namespace
     */
    public function log(): LoggingApiNamespace
    {
        return new LoggingApiNamespace($this->loggingApi, $this->script, $this->executionLog);
    }

    /**
     * Validation API namespace
     */
    public function validate(): ValidationApiNamespace
    {
        return new ValidationApiNamespace($this->validationApi, $this->script, $this->executionLog);
    }

    /**
     * Get current execution context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get script metadata
     */
    public function getScriptInfo(): array
    {
        return [
            'id' => $this->script->id,
            'name' => $this->script->name,
            'version' => $this->script->version,
            'client_id' => $this->script->client_id,
            'language' => $this->script->language,
        ];
    }

    /**
     * Get execution metadata
     */
    public function getExecutionInfo(): array
    {
        return [
            'id' => $this->executionLog->id,
            'trigger_type' => $this->executionLog->trigger_type,
            'started_at' => $this->executionLog->started_at?->toISOString(),
            'execution_time' => $this->executionLog->execution_time,
        ];
    }

    /**
     * Utility functions
     */
    public function utils(): UtilityApiNamespace
    {
        return new UtilityApiNamespace($this->script, $this->executionLog);
    }
}

/**
 * Database API namespace
 */
class DatabaseApiNamespace
{
    protected DatabaseApiService $databaseApi;
    protected Script $script;
    protected ScriptExecutionLog $executionLog;
    protected ScriptSecurityService $securityService;

    public function __construct(
        DatabaseApiService $databaseApi,
        Script $script,
        ScriptExecutionLog $executionLog,
        ScriptSecurityService $securityService
    ) {
        $this->databaseApi = $databaseApi;
        $this->script = $script;
        $this->executionLog = $executionLog;
        $this->securityService = $securityService;
    }

    /**
     * Execute a safe database query
     */
    public function query(string $query, array $bindings = []): string
    {
        $this->checkDatabaseAccess();
        return $this->databaseApi->executeQuery($this->script, $query, $bindings, $this->executionLog);
    }

    /**
     * Get records from a table
     */
    public function select(string $table, array $columns = ['*'], array $conditions = []): string
    {
        $this->checkDatabaseAccess();
        return $this->databaseApi->selectRecords($this->script, $table, $columns, $conditions, $this->executionLog);
    }

    /**
     * Insert a record
     */
    public function insert(string $table, array $data): string
    {
        $this->checkDatabaseAccess();
        return $this->databaseApi->insertRecord($this->script, $table, $data, $this->executionLog);
    }

    /**
     * Update records
     */
    public function update(string $table, array $data, array $conditions): string
    {
        $this->checkDatabaseAccess();
        return $this->databaseApi->updateRecords($this->script, $table, $data, $conditions, $this->executionLog);
    }

    /**
     * Delete records
     */
    public function delete(string $table, array $conditions): string
    {
        $this->checkDatabaseAccess();
        return $this->databaseApi->deleteRecords($this->script, $table, $conditions, $this->executionLog);
    }

    /**
     * Check database access permissions
     */
    protected function checkDatabaseAccess(): void
    {
        if (!$this->securityService->canAccessDatabase($this->script)) {
            throw new ApiAccessDeniedException('Database access denied for this script');
        }
    }
}

/**
 * HTTP API namespace
 */
class HttpApiNamespace
{
    protected HttpApiService $httpApi;
    protected Script $script;
    protected ScriptExecutionLog $executionLog;
    protected ScriptSecurityService $securityService;

    public function __construct(
        HttpApiService $httpApi,
        Script $script,
        ScriptExecutionLog $executionLog,
        ScriptSecurityService $securityService
    ) {
        $this->httpApi = $httpApi;
        $this->script = $script;
        $this->executionLog = $executionLog;
        $this->securityService = $securityService;
    }

    /**
     * Make HTTP GET request
     */
    public function get(string $url, array $headers = []): string
    {
        $this->checkHttpAccess($url);
        return $this->httpApi->makeRequest($this->script, 'GET', $url, [], $headers, $this->executionLog);
    }

    /**
     * Make HTTP POST request
     */
    public function post(string $url, array $data = [], array $headers = []): string
    {
        $this->checkHttpAccess($url);
        return $this->httpApi->makeRequest($this->script, 'POST', $url, $data, $headers, $this->executionLog);
    }

    /**
     * Make HTTP PUT request
     */
    public function put(string $url, array $data = [], array $headers = []): string
    {
        $this->checkHttpAccess($url);
        return $this->httpApi->makeRequest($this->script, 'PUT', $url, $data, $headers, $this->executionLog);
    }

    /**
     * Make HTTP DELETE request
     */
    public function delete(string $url, array $headers = []): string
    {
        $this->checkHttpAccess($url);
        return $this->httpApi->makeRequest($this->script, 'DELETE', $url, [], $headers, $this->executionLog);
    }

    /**
     * Check HTTP access permissions
     */
    protected function checkHttpAccess(string $url): void
    {
        if (!$this->securityService->canAccessUrl($this->script, $url)) {
            throw new ApiAccessDeniedException('HTTP access denied for URL: ' . $url);
        }
    }
}

/**
 * Event API namespace
 */
class EventApiNamespace
{
    protected EventApiService $eventApi;
    protected Script $script;
    protected ScriptExecutionLog $executionLog;
    protected ScriptSecurityService $securityService;

    public function __construct(
        EventApiService $eventApi,
        Script $script,
        ScriptExecutionLog $executionLog,
        ScriptSecurityService $securityService
    ) {
        $this->eventApi = $eventApi;
        $this->script = $script;
        $this->executionLog = $executionLog;
        $this->securityService = $securityService;
    }

    /**
     * Dispatch an event
     */
    public function dispatch(string $eventName, array $data = []): string
    {
        $this->checkEventAccess($eventName);
        return $this->eventApi->dispatchEvent($this->script, $eventName, $data, $this->executionLog);
    }

    /**
     * Check event access permissions
     */
    protected function checkEventAccess(string $eventName): void
    {
        if (!$this->securityService->canDispatchEvent($this->script, $eventName)) {
            throw new ApiAccessDeniedException('Event dispatch denied for: ' . $eventName);
        }
    }
}

/**
 * Logging API namespace
 */
class LoggingApiNamespace
{
    protected LoggingApiService $loggingApi;
    protected Script $script;
    protected ScriptExecutionLog $executionLog;

    public function __construct(
        LoggingApiService $loggingApi,
        Script $script,
        ScriptExecutionLog $executionLog
    ) {
        $this->loggingApi = $loggingApi;
        $this->script = $script;
        $this->executionLog = $executionLog;
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->loggingApi->log($this->script, 'info', $message, $context, $this->executionLog);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->loggingApi->log($this->script, 'warning', $message, $context, $this->executionLog);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->loggingApi->log($this->script, 'error', $message, $context, $this->executionLog);
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->loggingApi->log($this->script, 'debug', $message, $context, $this->executionLog);
    }
}

/**
 * Validation API namespace
 */
class ValidationApiNamespace
{
    protected ValidationApiService $validationApi;
    protected Script $script;
    protected ScriptExecutionLog $executionLog;

    public function __construct(
        ValidationApiService $validationApi,
        Script $script,
        ScriptExecutionLog $executionLog
    ) {
        $this->validationApi = $validationApi;
        $this->script = $script;
        $this->executionLog = $executionLog;
    }

    /**
     * Validate data against rules
     */
    public function validate(array $data, array $rules): array
    {
        return $this->validationApi->validateData($data, $rules, $this->script, $this->executionLog);
    }

    /**
     * Check if email is valid
     */
    public function isEmail(string $email): bool
    {
        return $this->validationApi->isValidEmail($email);
    }

    /**
     * Check if URL is valid
     */
    public function isUrl(string $url): bool
    {
        return $this->validationApi->isValidUrl($url);
    }

    /**
     * Sanitize string input
     */
    public function sanitize(string $input): string
    {
        return $this->validationApi->sanitizeInput($input);
    }
}

/**
 * Utility API namespace
 */
class UtilityApiNamespace
{
    protected Script $script;
    protected ScriptExecutionLog $executionLog;

    public function __construct(Script $script, ScriptExecutionLog $executionLog)
    {
        $this->script = $script;
        $this->executionLog = $executionLog;
    }

    /**
     * Get current timestamp
     */
    public function now(): string
    {
        return now()->toISOString();
    }

    /**
     * Generate UUID
     */
    public function uuid(): string
    {
        return (string) \Str::uuid();
    }

    /**
     * Sleep for specified seconds
     */
    public function sleep(int $seconds): void
    {
        if ($seconds > 5) {
            throw new \InvalidArgumentException('Sleep duration cannot exceed 5 seconds');
        }
        sleep($seconds);
    }

    /**
     * Generate random string
     */
    public function randomString(int $length = 10): string
    {
        if ($length > 100) {
            throw new \InvalidArgumentException('Random string length cannot exceed 100 characters');
        }
        return \Str::random($length);
    }

    /**
     * Hash string
     */
    public function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Encode to base64
     */
    public function base64Encode(string $value): string
    {
        return base64_encode($value);
    }

    /**
     * Decode from base64
     */
    public function base64Decode(string $value): string
    {
        return base64_decode($value);
    }

    /**
     * Parse JSON
     */
    public function parseJson(string $json): array
    {
        return json_decode($json, true) ?? [];
    }

    /**
     * Convert to JSON
     */
    public function toJson(array $data): string
    {
        return json_encode($data);
    }
}