<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ScriptingService;
use App\Services\DenoScriptingService;
use App\Services\Security\ScriptSecurityService;
use App\Services\Security\AstSecurityAnalyzer;
use App\Services\Scripting\ScriptingApiService;
use App\Services\Scripting\ResourceMonitorService;
use App\Services\Monitoring\WatchdogService;
use App\Services\Monitoring\KillSwitchService;
use App\Services\Monitoring\PrometheusMetricsService;
use App\Services\Monitoring\ScriptMonitoringService;
use App\Services\ScriptTriggerService;
use Illuminate\Support\Facades\Log;

class ScriptingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register monitoring services
        $this->app->singleton(PrometheusMetricsService::class);
        $this->app->singleton(ScriptMonitoringService::class);
        $this->app->singleton(KillSwitchService::class);
        $this->app->singleton(WatchdogService::class);
        
        // Register security services
        $this->app->singleton(ScriptSecurityService::class);
        $this->app->singleton(AstSecurityAnalyzer::class);
        
        // Register scripting API services
        $this->app->singleton(ScriptingApiService::class);
        $this->app->singleton(ResourceMonitorService::class);
        $this->app->singleton(ScriptTriggerService::class);
        
        // Register main scripting service
        $this->app->singleton(ScriptingService::class, function ($app) {
            $config = config('scripting');
            
            // Check if Deno is enabled and available
            if ($config['deno']['enabled'] ?? false) {
                try {
                    $denoService = $app->make(DenoScriptingService::class);
                    
                    // Check if Deno sidecar is available
                    if ($denoService->isDenoSidecarAvailable()) {
                        Log::info('Using Deno scripting service');
                        return $denoService;
                    }
                    
                    // Fallback to V8Js if configured
                    if ($config['deno']['fallback_to_v8js'] ?? false) {
                        Log::warning('Deno sidecar not available, falling back to V8Js');
                    } else {
                        throw new \RuntimeException('Deno sidecar is not available and fallback is disabled');
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to initialize Deno scripting service', [
                        'error' => $e->getMessage()
                    ]);
                    
                    if (!($config['deno']['fallback_to_v8js'] ?? false)) {
                        throw $e;
                    }
                }
            }
            
            // Fallback to V8Js implementation
            Log::info('Using V8Js scripting service');
            return new ScriptingService(
                $app->make(ScriptSecurityService::class),
                $app->make(ScriptingApiService::class),
                $app->make(ResourceMonitorService::class)
            );
        });
        
        // Register DenoScriptingService separately for direct access if needed
        $this->app->singleton(DenoScriptingService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Initialize monitoring services on boot
        if (config('scripting.monitoring.enable_monitoring')) {
            try {
                $this->app->make(PrometheusMetricsService::class)->initialize();
                $this->app->make(KillSwitchService::class)->initialize();
                
                Log::info('Scripting monitoring services initialized');
            } catch (\Exception $e) {
                Log::error('Failed to initialize monitoring services', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Register event listeners for script triggers
        if (config('scripting.enabled')) {
            $triggerService = $this->app->make(ScriptTriggerService::class);
            $triggerService->registerEventListeners();
        }
    }
}