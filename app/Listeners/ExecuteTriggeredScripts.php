<?php

namespace App\Listeners;

use App\Events\ScriptExecutionRequested;
use App\Models\Script;
use App\Services\ScriptingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ExecuteTriggeredScripts implements ShouldQueue
{
    use InteractsWithQueue;

    protected ScriptingService $scriptingService;

    /**
     * Create the event listener.
     */
    public function __construct(ScriptingService $scriptingService)
    {
        $this->scriptingService = $scriptingService;
    }

    /**
     * Handle the event.
     */
    public function handle(ScriptExecutionRequested $event): void
    {
        try {
            Log::info('Executing triggered script', [
                'script_id' => $event->script->id,
                'trigger_type' => $event->triggerType,
                'event_name' => $event->eventName,
                'user_id' => $event->user?->id,
            ]);

            // Execute the script
            $executionLog = $this->scriptingService->executeScript(
                $event->script,
                $event->context,
                $event->triggerType,
                $event->user?->id
            );

            if ($executionLog->wasSuccessful()) {
                Log::info('Triggered script executed successfully', [
                    'script_id' => $event->script->id,
                    'execution_log_id' => $executionLog->id,
                    'execution_time' => $executionLog->execution_time,
                ]);
            } else {
                Log::warning('Triggered script execution failed', [
                    'script_id' => $event->script->id,
                    'execution_log_id' => $executionLog->id,
                    'error' => $executionLog->error_message,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to execute triggered script', [
                'script_id' => $event->script->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw for proper queue handling
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(ScriptExecutionRequested $event, \Throwable $exception): void
    {
        Log::error('Script execution job failed', [
            'script_id' => $event->script->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}