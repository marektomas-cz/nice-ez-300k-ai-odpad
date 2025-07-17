@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Script Versions - {{ $script->name }}</h1>
    <div>
        <a href="{{ route('scripts.show', $script) }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Script
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVersionModal">
            <i class="fas fa-code-branch"></i> Create New Version
        </button>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Version History</h5>
            </div>
            <div class="card-body">
                @if($versions->count() > 0)
                    <div class="version-timeline">
                        @foreach($versions as $version)
                        <div class="version-item @if($version->is_current) current-version @endif">
                            <div class="version-marker"></div>
                            <div class="version-content">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            Version {{ $version->version_number }}
                                            @if($version->is_current)
                                                <span class="badge bg-success">Current</span>
                                            @endif
                                            @if($version->approval_status === 'pending')
                                                <span class="badge bg-warning">Pending Approval</span>
                                            @elseif($version->approval_status === 'approved')
                                                <span class="badge bg-info">Approved</span>
                                            @elseif($version->approval_status === 'rejected')
                                                <span class="badge bg-danger">Rejected</span>
                                            @endif
                                        </h6>
                                        <p class="text-muted mb-2">
                                            <small>
                                                Created by {{ $version->creator->name }} 
                                                on {{ $version->created_at->format('M d, Y H:i') }}
                                            </small>
                                        </p>
                                        <p class="mb-2">{{ $version->notes }}</p>
                                        
                                        @if($version->approval_status === 'approved' && $version->approved_by)
                                            <p class="text-success mb-2">
                                                <small>
                                                    Approved by {{ $version->approver->name }} 
                                                    on {{ $version->approved_at->format('M d, Y H:i') }}
                                                </small>
                                            </p>
                                        @elseif($version->approval_status === 'rejected' && $version->rejected_by)
                                            <p class="text-danger mb-2">
                                                <small>
                                                    Rejected by {{ $version->rejecter->name }} 
                                                    on {{ $version->rejected_at->format('M d, Y H:i') }}
                                                    <br>Reason: {{ $version->rejection_reason }}
                                                </small>
                                            </p>
                                        @endif
                                    </div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                onclick="viewVersion('{{ $version->id }}')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        @if(!$version->is_current && $version->approval_status === 'approved')
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="compareVersions('{{ $script->current_version_id }}', '{{ $version->id }}')">
                                                <i class="fas fa-exchange-alt"></i> Compare
                                            </button>
                                            @can('update', $script)
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        onclick="rollbackToVersion('{{ $version->id }}')">
                                                    <i class="fas fa-undo"></i> Rollback
                                                </button>
                                            @endcan
                                        @endif
                                        @if($version->approval_status === 'pending')
                                            @can('approve', $version)
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        onclick="approveVersion('{{ $version->id }}')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="rejectVersion('{{ $version->id }}')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </div>
                                
                                @if($version->changes_summary)
                                    <div class="mt-2">
                                        <small class="text-muted">Changes:</small>
                                        <pre class="changes-summary">{{ $version->changes_summary }}</pre>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    {{ $versions->links() }}
                @else
                    <p class="text-muted text-center">No versions created yet.</p>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Version Statistics</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-6">Total Versions:</dt>
                    <dd class="col-sm-6">{{ $script->versions()->count() }}</dd>
                    
                    <dt class="col-sm-6">Current Version:</dt>
                    <dd class="col-sm-6">{{ $script->currentVersion->version_number ?? 'N/A' }}</dd>
                    
                    <dt class="col-sm-6">Pending Approval:</dt>
                    <dd class="col-sm-6">{{ $script->versions()->where('approval_status', 'pending')->count() }}</dd>
                    
                    <dt class="col-sm-6">Last Update:</dt>
                    <dd class="col-sm-6">{{ $script->updated_at->diffForHumans() }}</dd>
                </dl>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Version Control Help</h6>
            </div>
            <div class="card-body">
                <p class="small">
                    <strong>Creating Versions:</strong><br>
                    Create a new version when you make significant changes to your script. Each version is tracked separately.
                </p>
                <p class="small">
                    <strong>Approval Process:</strong><br>
                    New versions may require approval before they can be used. Approvers will review your changes.
                </p>
                <p class="small">
                    <strong>Rollback:</strong><br>
                    You can rollback to any approved previous version if needed. The current version will be archived.
                </p>
                <p class="small mb-0">
                    <strong>Comparing Versions:</strong><br>
                    Use the compare feature to see the differences between any two versions side-by-side.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Create Version Modal -->
<div class="modal fade" id="createVersionModal" tabindex="-1" aria-labelledby="createVersionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('scripts.versions.store', $script) }}" id="createVersionForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createVersionModalLabel">Create New Version</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="version_notes" class="form-label">Version Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="version_notes" name="notes" rows="3" required 
                                  placeholder="Describe the changes in this version"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="version_code" class="form-label">Script Code</label>
                        <div id="versionMonacoEditor" style="height: 400px; border: 1px solid #dee2e6;"></div>
                        <textarea id="version_code" name="code" class="d-none" required>{{ $script->code }}</textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="submit_for_approval" name="submit_for_approval" value="1">
                        <label class="form-check-label" for="submit_for_approval">
                            Submit for approval immediately
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Version</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Version Diff Modal -->
<div class="modal fade" id="versionDiffModal" tabindex="-1" aria-labelledby="versionDiffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="versionDiffModalLabel">Version Comparison</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="diffEditor" style="height: 600px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Version Modal -->
<div class="modal fade" id="rejectVersionModal" tabindex="-1" aria-labelledby="rejectVersionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="rejectVersionForm">
                @csrf
                @method('POST')
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectVersionModalLabel">Reject Version</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejection_reason" name="reason" rows="3" required 
                                  placeholder="Please provide a reason for rejection"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Version</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.version-timeline {
    position: relative;
    padding-left: 30px;
}

.version-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #dee2e6;
}

.version-item {
    position: relative;
    padding-bottom: 2rem;
}

.version-marker {
    position: absolute;
    left: -25px;
    top: 5px;
    width: 12px;
    height: 12px;
    background-color: #6c757d;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.current-version .version-marker {
    background-color: #28a745;
    box-shadow: 0 0 0 2px #28a745;
}

.version-content {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.25rem;
    border: 1px solid #dee2e6;
}

.changes-summary {
    background-color: #fff;
    padding: 0.5rem;
    border-radius: 0.25rem;
    border: 1px solid #dee2e6;
    font-size: 0.875rem;
    margin-bottom: 0;
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('build/monaco-editor.js') }}"></script>
<script>
let versionEditor;
let diffEditor;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize version editor in modal
    const createVersionModal = document.getElementById('createVersionModal');
    createVersionModal.addEventListener('shown.bs.modal', function () {
        if (!versionEditor) {
            versionEditor = new ScriptEditor('versionMonacoEditor', {
                value: document.getElementById('version_code').value,
                theme: 'vs-dark',
                language: 'javascript'
            });
            
            versionEditor.editor.onDidChangeModelContent(() => {
                document.getElementById('version_code').value = versionEditor.getValue();
            });
        }
    });
});

function viewVersion(versionId) {
    fetch(`/scripts/{{ $script->id }}/versions/${versionId}`)
        .then(response => response.json())
        .then(data => {
            // Show version details in a modal or redirect
            alert('Version details: ' + JSON.stringify(data));
        });
}

function compareVersions(version1Id, version2Id) {
    fetch(`/scripts/{{ $script->id }}/versions/compare?v1=${version1Id}&v2=${version2Id}`)
        .then(response => response.json())
        .then(data => {
            showDiffModal(data.version1, data.version2);
        });
}

function showDiffModal(version1, version2) {
    const modal = new bootstrap.Modal(document.getElementById('versionDiffModal'));
    modal.show();
    
    // Initialize diff editor when modal is shown
    document.getElementById('versionDiffModal').addEventListener('shown.bs.modal', function () {
        if (diffEditor) {
            diffEditor.dispose();
        }
        
        require(['vs/editor/editor.main'], function() {
            const originalModel = monaco.editor.createModel(version1.code, 'javascript');
            const modifiedModel = monaco.editor.createModel(version2.code, 'javascript');
            
            diffEditor = monaco.editor.createDiffEditor(document.getElementById('diffEditor'), {
                theme: 'vs-dark',
                enableSplitViewResizing: false,
                renderSideBySide: true,
                readOnly: true
            });
            
            diffEditor.setModel({
                original: originalModel,
                modified: modifiedModel
            });
        });
    });
}

function rollbackToVersion(versionId) {
    if (confirm('Are you sure you want to rollback to this version? The current version will be archived.')) {
        fetch(`/scripts/{{ $script->id }}/versions/${versionId}/rollback`, {
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
                alert('Rollback failed: ' + data.message);
            }
        });
    }
}

function approveVersion(versionId) {
    if (confirm('Are you sure you want to approve this version?')) {
        fetch(`/scripts/{{ $script->id }}/versions/${versionId}/approve`, {
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
                alert('Approval failed: ' + data.message);
            }
        });
    }
}

function rejectVersion(versionId) {
    const modal = new bootstrap.Modal(document.getElementById('rejectVersionModal'));
    const form = document.getElementById('rejectVersionForm');
    form.action = `/scripts/{{ $script->id }}/versions/${versionId}/reject`;
    modal.show();
}
</script>
@endpush