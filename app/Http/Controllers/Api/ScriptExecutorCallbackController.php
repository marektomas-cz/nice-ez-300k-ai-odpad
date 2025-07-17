<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScriptExecutionLog;
use App\Services\Scripting\ScriptingApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class ScriptExecutorCallbackController extends Controller
{
    protected ScriptingApiService $apiService;

    public function __construct(ScriptingApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Handle API callbacks from Deno executor
     */
    public function handleCallback(Request $request): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'execution_id' => 'required|string',
            'api_token' => 'required|string',
            'method' => 'required|string',
            'type' => 'required|string|in:database,http,events',
            'params' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid request format',
                'details' => $validator->errors(),
            ], 400);
        }

        // Verify API token
        if (!$this->verifyApiToken($request->execution_id, $request->api_token)) {
            Log::warning('Invalid API token for script execution', [
                'execution_id' => $request->execution_id,
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Invalid API token',
            ], 401);
        }

        // Get execution log
        $executionLog = ScriptExecutionLog::find($request->execution_id);
        if (!$executionLog || $executionLog->status !== 'running') {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or inactive execution',
            ], 404);
        }

        try {
            // Process the API call based on type
            $result = match ($request->type) {
                'database' => $this->handleDatabaseCall($request->method, $request->params, $executionLog),
                'http' => $this->handleHttpCall($request->method, $request->params, $executionLog),
                'events' => $this->handleEventCall($request->method, $request->params, $executionLog),
                default => throw new \Exception('Unknown API type'),
            };

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling script API callback', [
                'execution_id' => $request->execution_id,
                'type' => $request->type,
                'method' => $request->method,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle database API calls
     */
    protected function handleDatabaseCall(string $method, array $params, ScriptExecutionLog $executionLog): mixed
    {
        $script = $executionLog->script;
        $api = $this->apiService->createSecureApi($script, [], $executionLog);

        return match ($method) {
            'query' => $api->database->query($params['sql'] ?? '', $params['bindings'] ?? []),
            'select' => $api->database->select(
                $params['table'] ?? '', 
                $params['columns'] ?? [], 
                $params['conditions'] ?? []
            ),
            'insert' => $api->database->insert($params['table'] ?? '', $params['data'] ?? []),
            'update' => $api->database->update(
                $params['table'] ?? '', 
                $params['data'] ?? [], 
                $params['conditions'] ?? []
            ),
            'delete' => $api->database->delete($params['table'] ?? '', $params['conditions'] ?? []),
            default => throw new \Exception("Unknown database method: {$method}"),
        };
    }

    /**
     * Handle HTTP API calls
     */
    protected function handleHttpCall(string $method, array $params, ScriptExecutionLog $executionLog): mixed
    {
        $script = $executionLog->script;
        $api = $this->apiService->createSecureApi($script, [], $executionLog);

        return match ($method) {
            'get' => $api->http->get($params['url'] ?? '', $params['headers'] ?? []),
            'post' => $api->http->post(
                $params['url'] ?? '', 
                $params['data'] ?? null, 
                $params['headers'] ?? []
            ),
            'put' => $api->http->put(
                $params['url'] ?? '', 
                $params['data'] ?? null, 
                $params['headers'] ?? []
            ),
            'patch' => $api->http->patch(
                $params['url'] ?? '', 
                $params['data'] ?? null, 
                $params['headers'] ?? []
            ),
            'delete' => $api->http->delete($params['url'] ?? '', $params['headers'] ?? []),
            default => throw new \Exception("Unknown HTTP method: {$method}"),
        };
    }

    /**
     * Handle event API calls
     */
    protected function handleEventCall(string $method, array $params, ScriptExecutionLog $executionLog): mixed
    {
        $script = $executionLog->script;
        $api = $this->apiService->createSecureApi($script, [], $executionLog);

        if ($method === 'dispatch') {
            $api->events->dispatch($params['eventName'] ?? '', $params['data'] ?? []);
            return ['dispatched' => true];
        }

        throw new \Exception("Unknown event method: {$method}");
    }

    /**
     * Verify API token
     */
    protected function verifyApiToken(string $executionId, string $token): bool
    {
        // Check if token exists in cache (for rate limiting)
        $cacheKey = "script_api_token:{$executionId}";
        $cachedToken = Cache::get($cacheKey);
        
        if ($cachedToken && $cachedToken === $token) {
            return true;
        }

        // Generate expected token
        $payload = [
            'execution_id' => $executionId,
            'expires_at' => time() + 3600,
            'nonce' => Cache::get("script_api_nonce:{$executionId}"),
        ];
        
        $secret = config('app.key');
        $expectedToken = hash_hmac('sha256', json_encode($payload), $secret);
        
        if (hash_equals($expectedToken, $token)) {
            // Cache the token for faster verification
            Cache::put($cacheKey, $token, 3600);
            return true;
        }

        return false;
    }
}