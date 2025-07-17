<?php

namespace App\Services;

use App\Models\Script;
use App\Models\Client;
use App\Models\User;
use App\Events\ScriptExecutionRequested;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ScriptTriggerService
{
    /**
     * Trigger scripts based on Laravel events
     */
    public function triggerScriptsForEvent(string $eventName, array $eventData = [], ?User $user = null): void
    {
        Log::info('Triggering scripts for event', [
            'event_name' => $eventName,
            'user_id' => $user?->id,
            'data_keys' => array_keys($eventData),
        ]);

        // Find scripts that should be triggered by this event
        $scripts = $this->getScriptsForEvent($eventName, $user);

        if ($scripts->isEmpty()) {
            Log::debug('No scripts found for event', ['event_name' => $eventName]);
            return;
        }

        Log::info('Found scripts to trigger', [
            'event_name' => $eventName,
            'script_count' => $scripts->count(),
            'script_ids' => $scripts->pluck('id')->toArray(),
        ]);

        // Dispatch execution events for each script
        foreach ($scripts as $script) {
            $this->dispatchScriptExecution($script, $eventData, 'event', $user, $eventName);
        }
    }

    /**
     * Trigger scripts for webhook events
     */
    public function triggerScriptsForWebhook(string $webhookName, array $payload, ?User $user = null): void
    {
        Log::info('Triggering scripts for webhook', [
            'webhook_name' => $webhookName,
            'user_id' => $user?->id,
            'payload_size' => strlen(json_encode($payload)),
        ]);

        // Find scripts configured for this webhook
        $scripts = $this->getScriptsForWebhook($webhookName, $user);

        if ($scripts->isEmpty()) {
            Log::debug('No scripts found for webhook', ['webhook_name' => $webhookName]);
            return;
        }

        // Prepare webhook context
        $context = [
            'webhook_name' => $webhookName,
            'payload' => $payload,
            'received_at' => now()->toISOString(),
            'user_id' => $user?->id,
        ];

        foreach ($scripts as $script) {
            $this->dispatchScriptExecution($script, $context, 'webhook', $user, $webhookName);
        }
    }

    /**
     * Trigger scripts for API events
     */
    public function triggerScriptsForApiEvent(string $apiEndpoint, string $method, array $data, ?User $user = null): void
    {
        Log::info('Triggering scripts for API event', [
            'api_endpoint' => $apiEndpoint,
            'method' => $method,
            'user_id' => $user?->id,
        ]);

        // Find scripts configured for this API endpoint
        $scripts = $this->getScriptsForApiEndpoint($apiEndpoint, $method, $user);

        if ($scripts->isEmpty()) {
            return;
        }

        $context = [
            'api_endpoint' => $apiEndpoint,
            'method' => $method,
            'data' => $data,
            'triggered_at' => now()->toISOString(),
            'user_id' => $user?->id,
        ];

        foreach ($scripts as $script) {
            $this->dispatchScriptExecution($script, $context, 'api', $user, $apiEndpoint);
        }
    }

    /**
     * Trigger scripts manually
     */
    public function triggerScriptManually(Script $script, array $context = [], ?User $user = null): void
    {
        Log::info('Triggering script manually', [
            'script_id' => $script->id,
            'user_id' => $user?->id,
        ]);

        $this->dispatchScriptExecution($script, $context, 'manual', $user);
    }

    /**
     * Get scripts that should be triggered by an event
     */
    protected function getScriptsForEvent(string $eventName, ?User $user = null): Collection
    {
        $query = Script::active()
            ->whereJsonContains('configuration->triggers->events', $eventName)
            ->orWhereJsonContains('configuration->triggers->events', '*') // Wildcard trigger
            ->with(['client']);

        // Filter by user's client if provided
        if ($user) {
            $query->where('client_id', $user->client_id);
        }

        return $query->get()->filter(function ($script) use ($eventName) {
            return $this->scriptMatchesEvent($script, $eventName);
        });
    }

    /**
     * Get scripts that should be triggered by a webhook
     */
    protected function getScriptsForWebhook(string $webhookName, ?User $user = null): Collection
    {
        $query = Script::active()
            ->whereJsonContains('configuration->triggers->webhooks', $webhookName)
            ->with(['client']);

        if ($user) {
            $query->where('client_id', $user->client_id);
        }

        return $query->get();
    }

    /**
     * Get scripts that should be triggered by an API endpoint
     */
    protected function getScriptsForApiEndpoint(string $endpoint, string $method, ?User $user = null): Collection
    {
        $query = Script::active()
            ->where(function ($q) use ($endpoint, $method) {
                $q->whereJsonContains('configuration->triggers->api_endpoints', [
                    'endpoint' => $endpoint,
                    'method' => $method,
                ])
                ->orWhereJsonContains('configuration->triggers->api_endpoints', [
                    'endpoint' => $endpoint,
                    'method' => '*', // Any method
                ]);
            })
            ->with(['client']);

        if ($user) {
            $query->where('client_id', $user->client_id);
        }

        return $query->get();
    }

    /**
     * Check if script matches the event
     */
    protected function scriptMatchesEvent(Script $script, string $eventName): bool
    {
        $triggers = $script->getConfigValue('triggers.events', []);
        
        if (empty($triggers)) {
            return false;
        }

        foreach ($triggers as $trigger) {
            if ($trigger === '*' || $trigger === $eventName) {
                return true;
            }
            
            // Support wildcard matching
            if (str_contains($trigger, '*')) {
                $pattern = str_replace('*', '.*', preg_quote($trigger, '/'));
                if (preg_match('/^' . $pattern . '$/', $eventName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Dispatch script execution event
     */
    protected function dispatchScriptExecution(
        Script $script,
        array $context,
        string $triggerType,
        ?User $user = null,
        string $eventName = ''
    ): void {
        try {
            Event::dispatch(new ScriptExecutionRequested(
                $script,
                $context,
                $triggerType,
                $user,
                $eventName
            ));

            Log::debug('Script execution dispatched', [
                'script_id' => $script->id,
                'trigger_type' => $triggerType,
                'event_name' => $eventName,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch script execution', [
                'script_id' => $script->id,
                'trigger_type' => $triggerType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register event listeners for script triggers
     */
    public function registerEventListeners(): void
    {
        // Register listeners for common Laravel events
        $eventMappings = [
            'user.created' => \App\Events\UserCreated::class,
            'user.updated' => \App\Events\UserUpdated::class,
            'user.deleted' => \App\Events\UserDeleted::class,
            'order.created' => \App\Events\OrderCreated::class,
            'order.updated' => \App\Events\OrderUpdated::class,
            'order.completed' => \App\Events\OrderCompleted::class,
            'payment.processed' => \App\Events\PaymentProcessed::class,
            'notification.sent' => \App\Events\NotificationSent::class,
        ];

        foreach ($eventMappings as $eventName => $eventClass) {
            Event::listen($eventClass, function ($event) use ($eventName) {
                $this->triggerScriptsForEvent($eventName, $this->extractEventData($event));
            });
        }

        // Register wildcard listener for any event
        Event::listen('*', function ($eventName, $data) {
            if (str_starts_with($eventName, 'script.trigger.')) {
                $triggerName = str_replace('script.trigger.', '', $eventName);
                $this->triggerScriptsForEvent($triggerName, $data[0] ?? []);
            }
        });
    }

    /**
     * Extract relevant data from event object
     */
    protected function extractEventData($event): array
    {
        $data = [];
        
        // Extract common properties
        $properties = ['id', 'name', 'email', 'status', 'amount', 'user_id', 'client_id'];
        
        foreach ($properties as $property) {
            if (isset($event->$property)) {
                $data[$property] = $event->$property;
            }
        }

        // Extract model data if available
        if (isset($event->model)) {
            $data['model'] = $event->model->toArray();
        }

        // Extract user data if available
        if (isset($event->user)) {
            $data['user'] = [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'email' => $event->user->email,
            ];
        }

        return $data;
    }

    /**
     * Get trigger configuration for a script
     */
    public function getTriggerConfiguration(Script $script): array
    {
        return [
            'events' => $script->getConfigValue('triggers.events', []),
            'webhooks' => $script->getConfigValue('triggers.webhooks', []),
            'api_endpoints' => $script->getConfigValue('triggers.api_endpoints', []),
            'schedule' => $script->getConfigValue('triggers.schedule', []),
        ];
    }

    /**
     * Update trigger configuration for a script
     */
    public function updateTriggerConfiguration(Script $script, array $config): void
    {
        $script->setConfigValue('triggers', $config);
        $script->save();

        Log::info('Script trigger configuration updated', [
            'script_id' => $script->id,
            'config' => $config,
        ]);
    }

    /**
     * Test trigger configuration
     */
    public function testTriggerConfiguration(Script $script, string $triggerType, array $testData = []): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'execution_log_id' => null,
            'execution_time' => null,
        ];

        try {
            switch ($triggerType) {
                case 'event':
                    $eventName = $testData['event_name'] ?? 'test.event';
                    $eventData = $testData['event_data'] ?? [];
                    $this->triggerScriptsForEvent($eventName, $eventData);
                    $result['success'] = true;
                    $result['message'] = 'Event trigger test executed successfully';
                    break;

                case 'webhook':
                    $webhookName = $testData['webhook_name'] ?? 'test.webhook';
                    $payload = $testData['payload'] ?? [];
                    $this->triggerScriptsForWebhook($webhookName, $payload);
                    $result['success'] = true;
                    $result['message'] = 'Webhook trigger test executed successfully';
                    break;

                case 'api':
                    $endpoint = $testData['endpoint'] ?? '/api/test';
                    $method = $testData['method'] ?? 'POST';
                    $data = $testData['data'] ?? [];
                    $this->triggerScriptsForApiEvent($endpoint, $method, $data);
                    $result['success'] = true;
                    $result['message'] = 'API trigger test executed successfully';
                    break;

                default:
                    $result['message'] = 'Unknown trigger type: ' . $triggerType;
            }

        } catch (\Exception $e) {
            $result['message'] = 'Trigger test failed: ' . $e->getMessage();
            Log::error('Trigger test failed', [
                'script_id' => $script->id,
                'trigger_type' => $triggerType,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}