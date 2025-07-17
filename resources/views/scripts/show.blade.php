@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>{{ $script->name }}</h1>
        <p class="text-muted mb-0">{{ $script->description }}</p>
    </div>
    <div class="btn-group">
        <a href="{{ route('scripts.edit', $script) }}" class="btn btn-primary">
            <i class="fas fa-edit"></i>
            Edit
        </a>
        <button type="button" class="btn btn-success" onclick="executeScript()">
            <i class="fas fa-play"></i>
            Execute
        </button>
        <div class="btn-group">
            <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ route('scripts.clone', $script) }}">
                    <i class="fas fa-copy"></i> Clone
                </a></li>
                <li><a class="dropdown-item" href="{{ route('scripts.export', $script) }}">
                    <i class="fas fa-download"></i> Export
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item" onclick="toggleStatus()">
                    <i class="fas fa-power-off"></i> 
                    {{ $script->is_active ? 'Deactivate' : 'Activate' }}
                </button></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('scripts.destroy', $script) }}" onsubmit="return confirm('Are you sure you want to delete this script?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Script Info -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number">
                <span class="status-badge {{ $script->is_active ? 'status-success' : 'status-pending' }}">
                    {{ $script->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <div class="stats-label">Status</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number">{{ $executionStats['total_executions'] }}</div>
            <div class="stats-label">Total Executions</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number">{{ number_format($executionStats['successful_executions'] / max($executionStats['total_executions'], 1) * 100, 1) }}%</div>
            <div class="stats-label">Success Rate</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number">{{ number_format($executionStats['average_execution_time'], 2) }}s</div>
            <div class="stats-label">Avg Execution Time</div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <div class="col-lg-8">
        <!-- Script Code -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Script Code</h5>
                <div>
                    <span class="badge bg-info">{{ strtoupper($script->language) }}</span>
                    <span class="badge bg-secondary">v{{ $script->version }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="script-editor">
                    <textarea id="code" readonly>{{ $script->code }}</textarea>
                </div>
            </div>
        </div>
        
        <!-- Recent Executions -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Executions</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="refreshExecutions()">
                    <i class="fas fa-refresh"></i>
                    Refresh
                </button>
            </div>
            <div class="card-body">
                <div id="executionsContainer">
                    @if($recentExecutions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Trigger</th>
                                        <th>Execution Time</th>
                                        <th>Memory Usage</th>
                                        <th>Started At</th>
                                        <th>Executor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentExecutions as $execution)
                                        <tr>
                                            <td>
                                                <span class="status-badge status-{{ $execution->status }}">
                                                    <i class="fas fa-{{ $execution->status === 'success' ? 'check-circle' : ($execution->status === 'failed' ? 'times-circle' : 'clock') }}"></i>
                                                    {{ ucfirst($execution->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ ucfirst($execution->trigger_type) }}</span>
                                            </td>
                                            <td>{{ $execution->formatted_execution_time }}</td>
                                            <td>{{ $execution->formatted_memory_usage }}</td>
                                            <td>{{ $execution->started_at?->format('M d, Y H:i:s') }}</td>
                                            <td>{{ $execution->executor->name ?? 'System' }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="showExecutionDetails({{ $execution->id }})">
                                                    <i class="fas fa-eye"></i>
                                                    Details
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-center">
                            {{ $recentExecutions->links() }}
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-play-circle fa-3x text-muted mb-3"></i>
                            <h6>No executions yet</h6>
                            <p class="text-muted">Execute this script to see execution history.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Script Metadata -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Script Information</h6>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-5">Created:</dt>
                    <dd class="col-sm-7">{{ $script->created_at->format('M d, Y H:i') }}</dd>
                    
                    <dt class="col-sm-5">Updated:</dt>
                    <dd class="col-sm-7">{{ $script->updated_at->format('M d, Y H:i') }}</dd>
                    
                    <dt class="col-sm-5">Created by:</dt>
                    <dd class="col-sm-7">{{ $script->creator->name }}</dd>
                    
                    <dt class="col-sm-5">Updated by:</dt>
                    <dd class="col-sm-7">{{ $script->updater->name }}</dd>
                    
                    <dt class="col-sm-5">Language:</dt>
                    <dd class="col-sm-7">{{ ucfirst($script->language) }}</dd>
                    
                    <dt class="col-sm-5">Version:</dt>
                    <dd class="col-sm-7">{{ $script->version }}</dd>
                </dl>
                
                @if($script->tags)
                    <div class="mt-3">
                        <strong>Tags:</strong><br>
                        @foreach($script->tags as $tag)
                            <span class="badge bg-secondary me-1">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Security Report -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Security Report</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Security Score:</span>
                    <span class="security-score security-{{ $securityReport['risk_level'] }}">
                        {{ number_format($securityReport['security_score'], 1) }}/100
                    </span>
                </div>
                
                <div class="progress mb-3">
                    <div class="progress-bar 
                        @if($securityReport['risk_level'] === 'low') bg-success 
                        @elseif($securityReport['risk_level'] === 'medium') bg-warning 
                        @elseif($securityReport['risk_level'] === 'high') bg-orange 
                        @else bg-danger @endif" 
                        style="width: {{ $securityReport['security_score'] }}%">
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>Risk Level:</strong>
                    <span class="badge bg-{{ $securityReport['risk_level'] === 'low' ? 'success' : ($securityReport['risk_level'] === 'medium' ? 'warning' : 'danger') }}">
                        {{ ucfirst($securityReport['risk_level']) }}
                    </span>
                </div>
                
                @if(!empty($securityReport['issues']))
                    <div class="mb-3">
                        <strong>Issues Found:</strong>
                        <ul class="list-unstyled mt-2">
                            @foreach($securityReport['issues'] as $issue)
                                <li><i class="fas fa-exclamation-triangle text-warning"></i> {{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                @if(!empty($securityReport['recommendations']))
                    <div>
                        <strong>Recommendations:</strong>
                        <ul class="list-unstyled mt-2">
                            @foreach($securityReport['recommendations'] as $recommendation)
                                <li><i class="fas fa-lightbulb text-info"></i> {{ $recommendation }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Configuration -->
        @if($script->configuration)
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">Configuration</h6>
                </div>
                <div class="card-body">
                    <pre class="language-json"><code>{{ json_encode($script->configuration, JSON_PRETTY_PRINT) }}</code></pre>
                </div>
            </div>
        @endif
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-success" onclick="executeScript()">
                        <i class="fas fa-play"></i>
                        Execute Script
                    </button>
                    <button class="btn btn-outline-secondary" onclick="showScheduleModal()">
                        <i class="fas fa-clock"></i>
                        Schedule Execution
                    </button>
                    <button class="btn btn-outline-info" onclick="showTestModal()">
                        <i class="fas fa-flask"></i>
                        Test Script
                    </button>
                    <button class="btn btn-outline-warning" onclick="showConfigModal()">
                        <i class="fas fa-cog"></i>
                        Configuration
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Execution Modal -->
<div class="modal fade" id="executionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Execute Script</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="executionForm">
                    <div class="mb-3">
                        <label class="form-label">Execution Context (JSON)</label>
                        <textarea id="executionContext" class="form-control" rows="5" placeholder='{"key": "value"}'></textarea>
                        <div class="form-text">Optional context data to pass to the script</div>
                    </div>
                </div>
                <div id="executionResult" style="display: none;">
                    <h6>Execution Result</h6>
                    <div id="executionOutput" class="execution-log"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="runScript()">
                    <i class="fas fa-play"></i>
                    Execute
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Execution Details Modal -->
<div class="modal fade" id="executionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Execution Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="executionDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let editor;
const executionModal = new bootstrap.Modal(document.getElementById('executionModal'));
const executionDetailsModal = new bootstrap.Modal(document.getElementById('executionDetailsModal'));

// Initialize CodeMirror
document.addEventListener('DOMContentLoaded', function() {
    editor = CodeMirror.fromTextArea(document.getElementById('code'), {
        mode: 'javascript',
        theme: 'monokai',
        lineNumbers: true,
        readOnly: true,
        matchBrackets: true,
        foldGutter: true,
        gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
        extraKeys: {
            'Ctrl-F': 'findPersistent'
        }
    });
});

function executeScript() {
    document.getElementById('executionContext').value = '';
    document.getElementById('executionResult').style.display = 'none';
    executionModal.show();
}

function runScript() {
    const context = document.getElementById('executionContext').value;
    let contextData = {};
    
    if (context.trim()) {
        try {
            contextData = JSON.parse(context);
        } catch (e) {
            alert('Invalid JSON in execution context');
            return;
        }
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executing...';
    btn.disabled = true;
    
    fetch(`/scripts/{{ $script->id }}/execute`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ context: contextData })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('executionResult').style.display = 'block';
        const output = document.getElementById('executionOutput');
        
        if (data.success) {
            output.innerHTML = `
                <div class="text-success mb-2">✓ Script executed successfully</div>
                <div><strong>Execution ID:</strong> ${data.execution_log.id}</div>
                <div><strong>Status:</strong> ${data.execution_log.status}</div>
                <div><strong>Execution Time:</strong> ${data.execution_log.execution_time || 'N/A'}</div>
                <div><strong>Output:</strong></div>
                <pre class="mt-2">${data.execution_log.output || 'No output'}</pre>
            `;
            
            // Refresh executions list
            setTimeout(refreshExecutions, 1000);
        } else {
            output.innerHTML = `
                <div class="text-danger mb-2">✗ Script execution failed</div>
                <div><strong>Error:</strong> ${data.message}</div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('executionResult').style.display = 'block';
        document.getElementById('executionOutput').innerHTML = `
            <div class="text-danger">✗ Execution failed: ${error.message}</div>
        `;
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function toggleStatus() {
    fetch(`/scripts/{{ $script->id }}/toggle-status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to toggle script status: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error toggling script status: ' + error.message);
    });
}

function showExecutionDetails(executionId) {
    fetch(`/executions/${executionId}`)
        .then(response => response.json())
        .then(data => {
            const content = document.getElementById('executionDetailsContent');
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Execution Information</h6>
                        <dl class="row">
                            <dt class="col-sm-4">ID:</dt>
                            <dd class="col-sm-8">${data.id}</dd>
                            <dt class="col-sm-4">Status:</dt>
                            <dd class="col-sm-8">
                                <span class="status-badge status-${data.status}">
                                    ${data.status}
                                </span>
                            </dd>
                            <dt class="col-sm-4">Trigger:</dt>
                            <dd class="col-sm-8">${data.trigger_type}</dd>
                            <dt class="col-sm-4">Started:</dt>
                            <dd class="col-sm-8">${data.started_at}</dd>
                            <dt class="col-sm-4">Completed:</dt>
                            <dd class="col-sm-8">${data.completed_at}</dd>
                            <dt class="col-sm-4">Duration:</dt>
                            <dd class="col-sm-8">${data.execution_time}s</dd>
                            <dt class="col-sm-4">Memory:</dt>
                            <dd class="col-sm-8">${data.memory_usage}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <h6>Output</h6>
                        <div class="execution-log" style="max-height: 200px;">
                            ${data.output || 'No output'}
                        </div>
                        ${data.error_message ? `
                            <h6 class="mt-3">Error</h6>
                            <div class="alert alert-danger">
                                ${data.error_message}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            executionDetailsModal.show();
        })
        .catch(error => {
            alert('Failed to load execution details: ' + error.message);
        });
}

function refreshExecutions() {
    fetch(`/scripts/{{ $script->id }}/execution-history`)
        .then(response => response.json())
        .then(data => {
            // Update executions table
            // This would require server-side rendering or a more complex frontend setup
            location.reload();
        })
        .catch(error => {
            console.error('Failed to refresh executions:', error);
        });
}

// Auto-refresh every 30 seconds
setInterval(() => {
    if (!document.hidden) {
        refreshExecutions();
    }
}, 30000);
</script>
@endpush