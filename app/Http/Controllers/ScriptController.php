<?php

namespace App\Http\Controllers;

use App\Models\Script;
use App\Models\ScriptExecutionLog;
use App\Services\ScriptingService;
use App\Services\Security\ScriptSecurityService;
use App\Http\Requests\StoreScriptRequest;
use App\Http\Requests\UpdateScriptRequest;
use App\Http\Resources\ScriptResource;
use App\Http\Resources\ScriptExecutionLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class ScriptController extends Controller
{
    protected ScriptingService $scriptingService;
    protected ScriptSecurityService $securityService;

    public function __construct(
        ScriptingService $scriptingService,
        ScriptSecurityService $securityService
    ) {
        $this->scriptingService = $scriptingService;
        $this->securityService = $securityService;
        
        $this->middleware('auth');
        $this->middleware('client.context');
    }

    /**
     * Display script management interface
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Script::class);

        $scripts = Script::byClient(Auth::user()->client_id)
            ->with(['creator', 'updater', 'executionLogs' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(5);
            }])
            ->when($request->search, function($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($request->status, function($query, $status) {
                $query->where('is_active', $status === 'active');
            })
            ->when($request->language, function($query, $language) {
                $query->where('language', $language);
            })
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        $stats = [
            'total_scripts' => Script::byClient(Auth::user()->client_id)->count(),
            'active_scripts' => Script::byClient(Auth::user()->client_id)->active()->count(),
            'total_executions' => ScriptExecutionLog::byClient(Auth::user()->client_id)->count(),
            'successful_executions' => ScriptExecutionLog::byClient(Auth::user()->client_id)
                ->successful()->count(),
            'failed_executions' => ScriptExecutionLog::byClient(Auth::user()->client_id)
                ->failed()->count(),
            'avg_execution_time' => ScriptExecutionLog::byClient(Auth::user()->client_id)
                ->successful()->avg('execution_time') ?? 0,
        ];

        return view('scripts.index', compact('scripts', 'stats'));
    }

    /**
     * Show script creation form
     */
    public function create(): View
    {
        $this->authorize('create', Script::class);

        return view('scripts.create');
    }

    /**
     * Store a new script
     */
    public function store(StoreScriptRequest $request): RedirectResponse
    {
        $this->authorize('create', Script::class);

        try {
            // Validate script syntax
            $syntaxValidation = $this->scriptingService->validateScriptSyntax($request->code);
            if (!$syntaxValidation['valid']) {
                return back()
                    ->withErrors(['code' => 'Script syntax error: ' . $syntaxValidation['error']])
                    ->withInput();
            }

            // Security validation
            $securityIssues = $this->securityService->validateScriptContent($request->code);
            if (!empty($securityIssues)) {
                return back()
                    ->withErrors(['code' => 'Security issues found: ' . implode(', ', $securityIssues)])
                    ->withInput();
            }

            $script = Script::create([
                'name' => $request->name,
                'description' => $request->description,
                'code' => $request->code,
                'language' => $request->language,
                'is_active' => $request->is_active ?? true,
                'client_id' => Auth::user()->client_id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'configuration' => $request->configuration ?? [],
                'tags' => $request->tags ?? [],
            ]);

            Log::info('Script created successfully', [
                'script_id' => $script->id,
                'user_id' => Auth::id(),
                'client_id' => Auth::user()->client_id,
            ]);

            return redirect()
                ->route('scripts.show', $script)
                ->with('success', 'Script created successfully.');

        } catch (\Exception $e) {
            Log::error('Error creating script', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'client_id' => Auth::user()->client_id,
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create script. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display script details
     */
    public function show(Script $script): View
    {
        $this->authorize('view', $script);

        $script->load(['creator', 'updater', 'client']);

        // Get execution statistics
        $executionStats = $this->scriptingService->getExecutionStats($script);

        // Get recent executions
        $recentExecutions = $script->executionLogs()
            ->with('executor')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Get security report
        $securityReport = $this->securityService->generateSecurityReport($script);

        return view('scripts.show', compact('script', 'executionStats', 'recentExecutions', 'securityReport'));
    }

    /**
     * Show script edit form
     */
    public function edit(Script $script): View
    {
        $this->authorize('update', $script);

        return view('scripts.edit', compact('script'));
    }

    /**
     * Update script
     */
    public function update(UpdateScriptRequest $request, Script $script): RedirectResponse
    {
        $this->authorize('update', $script);

        try {
            // Validate script syntax if code changed
            if ($request->code !== $script->code) {
                $syntaxValidation = $this->scriptingService->validateScriptSyntax($request->code);
                if (!$syntaxValidation['valid']) {
                    return back()
                        ->withErrors(['code' => 'Script syntax error: ' . $syntaxValidation['error']])
                        ->withInput();
                }

                // Security validation
                $securityIssues = $this->securityService->validateScriptContent($request->code);
                if (!empty($securityIssues)) {
                    return back()
                        ->withErrors(['code' => 'Security issues found: ' . implode(', ', $securityIssues)])
                        ->withInput();
                }
            }

            $script->update([
                'name' => $request->name,
                'description' => $request->description,
                'code' => $request->code,
                'is_active' => $request->is_active ?? $script->is_active,
                'updated_by' => Auth::id(),
                'configuration' => $request->configuration ?? $script->configuration,
                'tags' => $request->tags ?? $script->tags,
            ]);

            Log::info('Script updated successfully', [
                'script_id' => $script->id,
                'user_id' => Auth::id(),
                'client_id' => Auth::user()->client_id,
            ]);

            return redirect()
                ->route('scripts.show', $script)
                ->with('success', 'Script updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating script', [
                'script_id' => $script->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update script. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Delete script
     */
    public function destroy(Script $script): RedirectResponse
    {
        $this->authorize('delete', $script);

        try {
            $script->delete();

            Log::info('Script deleted successfully', [
                'script_id' => $script->id,
                'user_id' => Auth::id(),
                'client_id' => Auth::user()->client_id,
            ]);

            return redirect()
                ->route('scripts.index')
                ->with('success', 'Script deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Error deleting script', [
                'script_id' => $script->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->withErrors(['error' => 'Failed to delete script. Please try again.']);
        }
    }

    /**
     * Execute script manually
     */
    public function execute(Request $request, Script $script): JsonResponse
    {
        $this->authorize('execute', $script);

        try {
            // Validate execution context
            $request->validate([
                'context' => 'sometimes|array',
                'context.*' => 'string|max:1000',
            ]);

            $context = $request->context ?? [];
            
            // Execute script
            $executionLog = $this->scriptingService->executeScript(
                $script,
                $context,
                'manual',
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Script executed successfully',
                'execution_log' => new ScriptExecutionLogResource($executionLog),
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing script', [
                'script_id' => $script->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate script syntax
     */
    public function validateSyntax(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:65535',
        ]);

        $validation = $this->scriptingService->validateScriptSyntax($request->code);
        $securityIssues = $this->securityService->validateScriptContent($request->code);

        return response()->json([
            'syntax_valid' => $validation['valid'],
            'syntax_error' => $validation['error'] ?? null,
            'syntax_line' => $validation['line'] ?? null,
            'security_issues' => $securityIssues,
            'security_valid' => empty($securityIssues),
        ]);
    }

    /**
     * Get script execution history
     */
    public function executionHistory(Script $script): JsonResponse
    {
        $this->authorize('view', $script);

        $executions = $script->executionLogs()
            ->with('executor')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'executions' => ScriptExecutionLogResource::collection($executions),
            'pagination' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
        ]);
    }

    /**
     * Get script security report
     */
    public function securityReport(Script $script): JsonResponse
    {
        $this->authorize('view', $script);

        $report = $this->securityService->generateSecurityReport($script);

        return response()->json($report);
    }

    /**
     * Clone script
     */
    public function clone(Script $script): RedirectResponse
    {
        $this->authorize('create', Script::class);
        $this->authorize('view', $script);

        try {
            $clonedScript = $script->replicate();
            $clonedScript->name = $script->name . ' (Copy)';
            $clonedScript->created_by = Auth::id();
            $clonedScript->updated_by = Auth::id();
            $clonedScript->is_active = false; // Cloned scripts are inactive by default
            $clonedScript->save();

            Log::info('Script cloned successfully', [
                'original_script_id' => $script->id,
                'cloned_script_id' => $clonedScript->id,
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('scripts.edit', $clonedScript)
                ->with('success', 'Script cloned successfully. Please review and activate when ready.');

        } catch (\Exception $e) {
            Log::error('Error cloning script', [
                'script_id' => $script->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->withErrors(['error' => 'Failed to clone script. Please try again.']);
        }
    }

    /**
     * Toggle script activation status
     */
    public function toggleStatus(Script $script): JsonResponse
    {
        $this->authorize('update', $script);

        try {
            $script->update([
                'is_active' => !$script->is_active,
                'updated_by' => Auth::id(),
            ]);

            $status = $script->is_active ? 'activated' : 'deactivated';

            Log::info("Script {$status} successfully", [
                'script_id' => $script->id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Script {$status} successfully",
                'is_active' => $script->is_active,
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling script status', [
                'script_id' => $script->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle script status',
            ], 400);
        }
    }

    /**
     * Export script
     */
    public function export(Script $script): JsonResponse
    {
        $this->authorize('view', $script);

        $export = [
            'name' => $script->name,
            'description' => $script->description,
            'code' => $script->code,
            'language' => $script->language,
            'version' => $script->version,
            'configuration' => $script->configuration,
            'tags' => $script->tags,
            'exported_at' => now()->toISOString(),
            'exported_by' => Auth::user()->name,
        ];

        return response()->json($export);
    }

    /**
     * Import script
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Script::class);

        $request->validate([
            'script_data' => 'required|json',
        ]);

        try {
            $scriptData = json_decode($request->script_data, true);

            // Validate imported data
            $validator = validator($scriptData, [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'code' => 'required|string|max:65535',
                'language' => 'required|string|in:javascript',
                'configuration' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return back()
                    ->withErrors(['script_data' => 'Invalid script data format'])
                    ->withInput();
            }

            // Security validation
            $securityIssues = $this->securityService->validateScriptContent($scriptData['code']);
            if (!empty($securityIssues)) {
                return back()
                    ->withErrors(['script_data' => 'Security issues found: ' . implode(', ', $securityIssues)])
                    ->withInput();
            }

            $script = Script::create([
                'name' => $scriptData['name'] . ' (Imported)',
                'description' => $scriptData['description'] ?? null,
                'code' => $scriptData['code'],
                'language' => $scriptData['language'],
                'is_active' => false, // Imported scripts are inactive by default
                'client_id' => Auth::user()->client_id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'configuration' => $scriptData['configuration'] ?? [],
                'tags' => $scriptData['tags'] ?? [],
            ]);

            Log::info('Script imported successfully', [
                'script_id' => $script->id,
                'user_id' => Auth::id(),
            ]);

            return redirect()
                ->route('scripts.show', $script)
                ->with('success', 'Script imported successfully. Please review and activate when ready.');

        } catch (\Exception $e) {
            Log::error('Error importing script', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->withErrors(['error' => 'Failed to import script. Please try again.'])
                ->withInput();
        }
    }
}