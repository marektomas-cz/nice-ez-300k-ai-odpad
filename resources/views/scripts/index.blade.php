@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Scripts</h1>
    <a href="{{ route('scripts.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        Create Script
    </a>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number">{{ $stats['total_scripts'] }}</div>
            <div class="stats-label">Total Scripts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number">{{ $stats['active_scripts'] }}</div>
            <div class="stats-label">Active Scripts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number">{{ number_format($stats['successful_executions']) }}</div>
            <div class="stats-label">Successful Executions</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-number">{{ number_format($stats['avg_execution_time'], 2) }}s</div>
            <div class="stats-label">Avg Execution Time</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('scripts.index') }}" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search scripts..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="language" class="form-select">
                    <option value="">All Languages</option>
                    <option value="javascript" {{ request('language') == 'javascript' ? 'selected' : '' }}>JavaScript</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i>
                    Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('scripts.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                    Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Scripts Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Scripts</h5>
    </div>
    <div class="card-body">
        @if($scripts->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Language</th>
                            <th>Executions</th>
                            <th>Success Rate</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scripts as $script)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $script->name }}</strong>
                                        @if($script->description)
                                            <br>
                                            <small class="text-muted">{{ Str::limit($script->description, 50) }}</small>
                                        @endif
                                        @if($script->tags)
                                            <br>
                                            @foreach($script->tags as $tag)
                                                <span class="badge bg-secondary">{{ $tag }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge {{ $script->is_active ? 'status-success' : 'status-pending' }}">
                                        <i class="fas fa-{{ $script->is_active ? 'check-circle' : 'pause-circle' }}"></i>
                                        {{ $script->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ strtoupper($script->language) }}</span>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <strong>{{ $script->executionLogs->count() }}</strong>
                                        @if($script->executionLogs->count() > 0)
                                            <br>
                                            <small class="text-muted">
                                                Last: {{ $script->executionLogs->first()->created_at->diffForHumans() }}
                                            </small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $totalExecutions = $script->executionLogs->count();
                                        $successfulExecutions = $script->executionLogs->where('status', 'success')->count();
                                        $successRate = $totalExecutions > 0 ? ($successfulExecutions / $totalExecutions) * 100 : 0;
                                    @endphp
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" style="width: {{ $successRate }}%"></div>
                                            </div>
                                        </div>
                                        <small class="ms-2">{{ number_format($successRate, 1) }}%</small>
                                    </div>
                                </td>
                                <td>
                                    {{ $script->updated_at->diffForHumans() }}
                                    <br>
                                    <small class="text-muted">by {{ $script->updater->name }}</small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('scripts.show', $script) }}" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('scripts.edit', $script) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="executeScript({{ $script->id }})" title="Execute">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="toggleStatus({{ $script->id }})" title="Toggle Status">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="{{ route('scripts.clone', $script) }}">Clone</a></li>
                                                <li><a class="dropdown-item" href="{{ route('scripts.export', $script) }}">Export</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="{{ route('scripts.destroy', $script) }}" onsubmit="return confirm('Are you sure you want to delete this script?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-center">
                {{ $scripts->links() }}
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-code fa-3x text-muted mb-3"></i>
                <h4>No scripts found</h4>
                <p class="text-muted">Get started by creating your first script.</p>
                <a href="{{ route('scripts.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Script
                </a>
            </div>
        @endif
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
@endsection

@push('scripts')
<script>
let currentScriptId = null;
const executionModal = new bootstrap.Modal(document.getElementById('executionModal'));

function executeScript(scriptId) {
    currentScriptId = scriptId;
    document.getElementById('executionContext').value = '';
    document.getElementById('executionResult').style.display = 'none';
    executionModal.show();
}

function runScript() {
    if (!currentScriptId) return;
    
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
    
    fetch(`/scripts/${currentScriptId}/execute`, {
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

function toggleStatus(scriptId) {
    fetch(`/scripts/${scriptId}/toggle-status`, {
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

// Auto-refresh every 30 seconds
setInterval(() => {
    if (!document.hidden) {
        location.reload();
    }
}, 30000);
</script>
@endpush