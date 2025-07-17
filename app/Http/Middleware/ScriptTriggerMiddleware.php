<?php

namespace App\Http\Middleware;

use App\Services\ScriptTriggerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ScriptTriggerMiddleware
{
    protected ScriptTriggerService $scriptTriggerService;

    public function __construct(ScriptTriggerService $scriptTriggerService)
    {
        $this->scriptTriggerService = $scriptTriggerService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only trigger on successful responses
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->triggerApiScripts($request, $response);
        }

        return $response;
    }

    /**
     * Trigger scripts for API endpoints
     */
    protected function triggerApiScripts(Request $request, Response $response): void
    {
        $endpoint = $request->getPathInfo();
        $method = $request->getMethod();
        
        // Skip internal API endpoints
        if (str_starts_with($endpoint, '/api/scripts') || str_starts_with($endpoint, '/api/internal')) {
            return;
        }

        $data = [
            'endpoint' => $endpoint,
            'method' => $method,
            'parameters' => $request->all(),
            'headers' => $request->headers->all(),
            'response_status' => $response->getStatusCode(),
            'timestamp' => now()->toISOString(),
        ];

        $this->scriptTriggerService->triggerScriptsForApiEvent(
            $endpoint,
            $method,
            $data,
            Auth::user()
        );
    }
}