<?php

namespace App\Console\Commands;

use App\Models\Script;
use App\Models\Client;
use App\Services\ScriptingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExecuteScheduledScriptsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'scripts:execute-scheduled 
                           {--client= : Execute scripts for specific client}
                           {--script= : Execute specific script}
                           {--dry-run : Show scripts that would be executed without running them}';

    /**
     * The console command description.
     */
    protected $description = 'Execute scheduled scripts based on their configuration';

    protected ScriptingService $scriptingService;

    public function __construct(ScriptingService $scriptingService)
    {
        parent::__construct();
        $this->scriptingService = $scriptingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting scheduled script execution...');

        $clientId = $this->option('client');
        $scriptId = $this->option('script');
        $dryRun = $this->option('dry-run');

        // Get scripts that should be executed
        $scripts = $this->getScheduledScripts($clientId, $scriptId);

        if ($scripts->isEmpty()) {
            $this->info('No scheduled scripts found.');
            return 0;
        }

        $this->info(sprintf('Found %d scheduled script(s) to execute.', $scripts->count()));

        if ($dryRun) {
            $this->showDryRunResults($scripts);
            return 0;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($scripts as $script) {
            try {
                $this->info(sprintf('Executing script: %s (ID: %d)', $script->name, $script->id));

                $context = $this->getExecutionContext($script);
                
                $executionLog = $this->scriptingService->executeScript(
                    $script,
                    $context,
                    'scheduled',
                    null // System execution
                );

                if ($executionLog->wasSuccessful()) {
                    $successCount++;
                    $this->info(sprintf('✓ Script executed successfully in %s seconds', 
                        number_format($executionLog->execution_time, 2)));
                } else {
                    $failureCount++;
                    $this->error(sprintf('✗ Script execution failed: %s', $executionLog->error_message));
                }

                // Update last execution time
                $this->updateLastExecutionTime($script);

            } catch (\Exception $e) {
                $failureCount++;
                $this->error(sprintf('✗ Script execution failed: %s', $e->getMessage()));
                
                Log::error('Scheduled script execution failed', [
                    'script_id' => $script->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info(sprintf(
            'Execution completed. Success: %d, Failures: %d',
            $successCount,
            $failureCount
        ));

        return $failureCount > 0 ? 1 : 0;
    }

    /**
     * Get scripts that should be executed based on schedule
     */
    protected function getScheduledScripts(?string $clientId, ?string $scriptId)
    {
        $query = Script::active()
            ->with(['client'])
            ->whereJsonContains('configuration->schedule->enabled', true);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if ($scriptId) {
            $query->where('id', $scriptId);
        }

        return $query->get()->filter(function ($script) {
            return $this->shouldExecuteScript($script);
        });
    }

    /**
     * Determine if a script should be executed based on its schedule
     */
    protected function shouldExecuteScript(Script $script): bool
    {
        $schedule = $script->getConfigValue('schedule', []);
        
        if (!($schedule['enabled'] ?? false)) {
            return false;
        }

        $frequency = $schedule['frequency'] ?? 'daily';
        $lastExecution = $script->getConfigValue('schedule.last_execution');
        
        if (!$lastExecution) {
            return true; // First execution
        }

        $lastExecutionTime = Carbon::parse($lastExecution);
        $now = Carbon::now();

        switch ($frequency) {
            case 'minutely':
                return $now->diffInMinutes($lastExecutionTime) >= 1;
            
            case 'hourly':
                return $now->diffInHours($lastExecutionTime) >= 1;
            
            case 'daily':
                return $now->diffInDays($lastExecutionTime) >= 1;
            
            case 'weekly':
                return $now->diffInWeeks($lastExecutionTime) >= 1;
            
            case 'monthly':
                return $now->diffInMonths($lastExecutionTime) >= 1;
            
            case 'cron':
                return $this->shouldExecuteByCron($schedule['cron'] ?? '', $lastExecutionTime);
            
            default:
                return false;
        }
    }

    /**
     * Check if script should execute based on cron expression
     */
    protected function shouldExecuteByCron(string $cronExpression, Carbon $lastExecution): bool
    {
        if (empty($cronExpression)) {
            return false;
        }

        try {
            // Simple cron parsing - in production, use a proper cron parser library
            $cron = new \Cron\CronExpression($cronExpression);
            $nextRun = $cron->getNextRunDate($lastExecution);
            
            return Carbon::now()->gte($nextRun);
        } catch (\Exception $e) {
            Log::warning('Invalid cron expression', [
                'expression' => $cronExpression,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get execution context for scheduled script
     */
    protected function getExecutionContext(Script $script): array
    {
        return [
            'scheduled_at' => Carbon::now()->toISOString(),
            'execution_type' => 'scheduled',
            'frequency' => $script->getConfigValue('schedule.frequency', 'daily'),
            'cron_expression' => $script->getConfigValue('schedule.cron'),
        ];
    }

    /**
     * Update last execution time for script
     */
    protected function updateLastExecutionTime(Script $script): void
    {
        $script->setConfigValue('schedule.last_execution', Carbon::now()->toISOString());
        $script->save();
    }

    /**
     * Show dry run results
     */
    protected function showDryRunResults($scripts): void
    {
        $this->info('Dry run results:');
        $this->line('');

        $headers = ['Script ID', 'Name', 'Client', 'Frequency', 'Last Execution', 'Would Execute'];
        $rows = [];

        foreach ($scripts as $script) {
            $schedule = $script->getConfigValue('schedule', []);
            $lastExecution = $script->getConfigValue('schedule.last_execution');
            
            $rows[] = [
                $script->id,
                $script->name,
                $script->client->name,
                $schedule['frequency'] ?? 'daily',
                $lastExecution ? Carbon::parse($lastExecution)->diffForHumans() : 'Never',
                $this->shouldExecuteScript($script) ? '✓ Yes' : '✗ No'
            ];
        }

        $this->table($headers, $rows);
    }
}