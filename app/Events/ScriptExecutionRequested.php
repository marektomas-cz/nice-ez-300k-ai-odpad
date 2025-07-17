<?php

namespace App\Events;

use App\Models\Script;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScriptExecutionRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Script $script;
    public array $context;
    public string $triggerType;
    public ?User $user;
    public string $eventName;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Script $script,
        array $context = [],
        string $triggerType = 'event',
        ?User $user = null,
        string $eventName = ''
    ) {
        $this->script = $script;
        $this->context = $context;
        $this->triggerType = $triggerType;
        $this->user = $user;
        $this->eventName = $eventName;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}