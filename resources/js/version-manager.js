import * as monaco from 'monaco-editor';

export class VersionManager {
    constructor() {
        this.currentScript = null;
        this.versions = [];
        this.diffEditor = null;
        this.selectedVersions = { original: null, modified: null };
        this.approvalWorkflow = { enabled: true, canApprove: false };
    }

    static init() {
        return new VersionManager().initialize();
    }

    initialize() {
        this.loadScript();
        this.setupEventListeners();
        this.initializeDiffEditor();
        this.initializeApprovalWorkflow();
    }

    loadScript() {
        const scriptId = document.getElementById('script-id')?.value;
        if (!scriptId) return;

        fetch(`/api/scripts/${scriptId}/versions`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.currentScript = data.script;
                    this.versions = data.versions;
                    this.renderVersionList();
                }
            })
            .catch(error => {
                console.error('Error loading script versions:', error);
            });
    }

    setupEventListeners() {
        // Create new version button
        const createVersionBtn = document.getElementById('create-version');
        if (createVersionBtn) {
            createVersionBtn.addEventListener('click', () => this.createVersion());
        }

        // Rollback button
        const rollbackBtn = document.getElementById('rollback-version');
        if (rollbackBtn) {
            rollbackBtn.addEventListener('click', () => this.rollbackVersion());
        }

        // Compare button
        const compareBtn = document.getElementById('compare-versions');
        if (compareBtn) {
            compareBtn.addEventListener('click', () => this.compareVersions());
        }

        // Export version button
        const exportBtn = document.getElementById('export-version');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportVersion());
        }

        // Approval workflow buttons
        const approveBtn = document.getElementById('approve-version');
        if (approveBtn) {
            approveBtn.addEventListener('click', () => this.approveVersion());
        }

        const rejectBtn = document.getElementById('reject-version');
        if (rejectBtn) {
            rejectBtn.addEventListener('click', () => this.rejectVersion());
        }

        const submitForApprovalBtn = document.getElementById('submit-for-approval');
        if (submitForApprovalBtn) {
            submitForApprovalBtn.addEventListener('click', () => this.submitForApproval());
        }
    }

    initializeDiffEditor() {
        const container = document.getElementById('version-diff');
        if (!container) return;

        this.diffEditor = monaco.editor.createDiffEditor(container, {
            theme: 'vs-dark',
            automaticLayout: true,
            renderSideBySide: true,
            readOnly: true,
            originalEditable: false,
            modifiedEditable: false,
            diffCodeLens: true,
            renderLineHighlight: 'all',
            scrollBeyondLastLine: false,
            minimap: {
                enabled: true
            }
        });
    }

    renderVersionList() {
        const container = document.getElementById('version-list');
        if (!container) return;

        container.innerHTML = '';

        this.versions.forEach(version => {
            const versionElement = this.createVersionElement(version);
            container.appendChild(versionElement);
        });
    }

    createVersionElement(version) {
        const element = document.createElement('div');
        element.className = 'version-item';
        element.dataset.versionId = version.id;

        const isActive = version.id === this.currentScript?.active_version_id;
        if (isActive) {
            element.classList.add('active');
        }

        element.innerHTML = `
            <div class="version-header">
                <div class="version-info">
                    <span class="version-number">v${version.version}</span>
                    <span class="version-date">${this.formatDate(version.created_at)}</span>
                    ${isActive ? '<span class="active-badge">Active</span>' : ''}
                </div>
                <div class="version-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick="versionManager.selectVersion(${version.id}, 'original')">
                        Select as Original
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="versionManager.selectVersion(${version.id}, 'modified')">
                        Select as Modified
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="versionManager.rollbackToVersion(${version.id})">
                        Rollback
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="versionManager.deleteVersion(${version.id})">
                        Delete
                    </button>
                </div>
            </div>
            <div class="version-details">
                <div class="version-author">
                    <strong>Author:</strong> ${version.created_by?.name || 'Unknown'}
                </div>
                <div class="version-description">
                    <strong>Description:</strong> ${version.description || 'No description'}
                </div>
                <div class="version-stats">
                    <span class="stat">
                        <i class="icon-code"></i>
                        ${version.code_size || 0} characters
                    </span>
                    <span class="stat">
                        <i class="icon-changes"></i>
                        ${version.changes_count || 0} changes
                    </span>
                    <span class="stat">
                        <i class="icon-clock"></i>
                        ${this.formatDate(version.created_at)}
                    </span>
                </div>
            </div>
            <div class="version-preview">
                <details>
                    <summary>Show Code Preview</summary>
                    <pre><code>${this.truncateCode(version.code)}</code></pre>
                </details>
            </div>
        `;

        return element;
    }

    selectVersion(versionId, type) {
        this.selectedVersions[type] = versionId;
        
        // Update UI to show selection
        document.querySelectorAll('.version-item').forEach(el => {
            el.classList.remove(`selected-${type}`);
        });
        
        document.querySelector(`[data-version-id="${versionId}"]`)?.classList.add(`selected-${type}`);
        
        // Update compare button state
        const compareBtn = document.getElementById('compare-versions');
        if (compareBtn) {
            compareBtn.disabled = !(this.selectedVersions.original && this.selectedVersions.modified);
        }
    }

    createVersion() {
        const description = prompt('Enter version description:');
        if (!description) return;

        const scriptId = document.getElementById('script-id')?.value;
        const currentCode = window.ScriptEditor?.editor?.getValue();
        
        if (!scriptId || !currentCode) {
            alert('Cannot create version: missing script or code');
            return;
        }

        fetch(`/api/scripts/${scriptId}/versions`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                code: currentCode,
                description: description
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadScript(); // Refresh version list
                this.showNotification('Version created successfully', 'success');
            } else {
                this.showNotification(data.message || 'Error creating version', 'error');
            }
        })
        .catch(error => {
            console.error('Error creating version:', error);
            this.showNotification('Error creating version', 'error');
        });
    }

    rollbackToVersion(versionId) {
        if (!confirm('Are you sure you want to rollback to this version? This will create a new version with the old code.')) {
            return;
        }

        const scriptId = document.getElementById('script-id')?.value;
        
        fetch(`/api/scripts/${scriptId}/rollback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                version_id: versionId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadScript(); // Refresh version list
                // Update main editor if available
                if (window.ScriptEditor?.editor) {
                    window.ScriptEditor.editor.setValue(data.code);
                }
                this.showNotification('Rollback completed successfully', 'success');
            } else {
                this.showNotification(data.message || 'Error during rollback', 'error');
            }
        })
        .catch(error => {
            console.error('Error during rollback:', error);
            this.showNotification('Error during rollback', 'error');
        });
    }

    compareVersions() {
        const originalId = this.selectedVersions.original;
        const modifiedId = this.selectedVersions.modified;
        
        if (!originalId || !modifiedId) {
            alert('Please select both original and modified versions');
            return;
        }

        // Load both versions
        Promise.all([
            fetch(`/api/scripts/versions/${originalId}`).then(r => r.json()),
            fetch(`/api/scripts/versions/${modifiedId}`).then(r => r.json())
        ])
        .then(([originalData, modifiedData]) => {
            if (originalData.success && modifiedData.success) {
                this.showDiff(originalData.code, modifiedData.code);
            } else {
                this.showNotification('Error loading versions for comparison', 'error');
            }
        })
        .catch(error => {
            console.error('Error comparing versions:', error);
            this.showNotification('Error comparing versions', 'error');
        });
    }

    showDiff(originalCode, modifiedCode) {
        if (!this.diffEditor) return;

        const originalModel = monaco.editor.createModel(originalCode, 'javascript');
        const modifiedModel = monaco.editor.createModel(modifiedCode, 'javascript');

        this.diffEditor.setModel({
            original: originalModel,
            modified: modifiedModel
        });

        // Show diff container
        const diffContainer = document.getElementById('version-diff');
        if (diffContainer) {
            diffContainer.style.display = 'block';
            diffContainer.scrollIntoView({ behavior: 'smooth' });
        }
    }

    deleteVersion(versionId) {
        if (!confirm('Are you sure you want to delete this version? This action cannot be undone.')) {
            return;
        }

        fetch(`/api/scripts/versions/${versionId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadScript(); // Refresh version list
                this.showNotification('Version deleted successfully', 'success');
            } else {
                this.showNotification(data.message || 'Error deleting version', 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting version:', error);
            this.showNotification('Error deleting version', 'error');
        });
    }

    exportVersion() {
        const versionId = this.selectedVersions.original || this.selectedVersions.modified;
        if (!versionId) {
            alert('Please select a version to export');
            return;
        }

        window.open(`/api/scripts/versions/${versionId}/export`, '_blank');
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleString();
    }

    truncateCode(code) {
        return code.length > 200 ? code.substring(0, 200) + '...' : code;
    }

    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    initializeApprovalWorkflow() {
        // Check user permissions
        const userRole = document.querySelector('meta[name="user-role"]')?.getAttribute('content');
        this.approvalWorkflow.canApprove = userRole === 'admin' || userRole === 'approver';
        
        // Update UI based on permissions
        this.updateApprovalUI();
    }

    updateApprovalUI() {
        const approvalSection = document.getElementById('approval-section');
        if (!approvalSection) return;

        if (this.approvalWorkflow.enabled) {
            approvalSection.style.display = 'block';
            this.renderApprovalControls();
        } else {
            approvalSection.style.display = 'none';
        }
    }

    renderApprovalControls() {
        const approvalSection = document.getElementById('approval-section');
        if (!approvalSection) return;

        const canApprove = this.approvalWorkflow.canApprove;
        const pendingVersions = this.versions.filter(v => v.status === 'pending');

        approvalSection.innerHTML = `
            <div class="approval-controls">
                <h4>Approval Workflow</h4>
                ${pendingVersions.length > 0 ? `
                    <div class="pending-versions">
                        <h5>Pending Approval (${pendingVersions.length})</h5>
                        ${pendingVersions.map(version => `
                            <div class="pending-version-item">
                                <div class="version-info">
                                    <span class="version-number">v${version.version}</span>
                                    <span class="version-author">by ${version.created_by?.name || 'Unknown'}</span>
                                    <span class="version-date">${this.formatDate(version.created_at)}</span>
                                </div>
                                ${canApprove ? `
                                    <div class="approval-actions">
                                        <button class="btn btn-success btn-sm" onclick="versionManager.approveVersionById(${version.id})">
                                            Approve
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="versionManager.rejectVersionById(${version.id})">
                                            Reject
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                ` : '<p>No versions pending approval</p>'}
                ${!canApprove ? `
                    <div class="submit-for-approval">
                        <button id="submit-for-approval" class="btn btn-primary">
                            Submit Current Version for Approval
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }

    submitForApproval() {
        const scriptId = document.getElementById('script-id')?.value;
        const currentCode = window.ScriptEditor?.editor?.getValue();
        
        if (!scriptId || !currentCode) {
            this.showNotification('Cannot submit for approval: missing script or code', 'error');
            return;
        }

        const description = prompt('Enter description for this version:');
        if (!description) return;

        fetch(`/api/scripts/${scriptId}/versions`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                code: currentCode,
                description: description,
                status: 'pending'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadScript(); // Refresh version list
                this.showNotification('Version submitted for approval', 'success');
            } else {
                this.showNotification(data.message || 'Error submitting version', 'error');
            }
        })
        .catch(error => {
            console.error('Error submitting version:', error);
            this.showNotification('Error submitting version', 'error');
        });
    }

    approveVersionById(versionId) {
        if (!confirm('Are you sure you want to approve this version?')) {
            return;
        }

        fetch(`/api/scripts/versions/${versionId}/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadScript(); // Refresh version list
                this.showNotification('Version approved successfully', 'success');
            } else {
                this.showNotification(data.message || 'Error approving version', 'error');
            }
        })
        .catch(error => {
            console.error('Error approving version:', error);
            this.showNotification('Error approving version', 'error');
        });
    }

    rejectVersionById(versionId) {
        const reason = prompt('Please enter the reason for rejection:');
        if (!reason) return;

        fetch(`/api/scripts/versions/${versionId}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadScript(); // Refresh version list
                this.showNotification('Version rejected', 'success');
            } else {
                this.showNotification(data.message || 'Error rejecting version', 'error');
            }
        })
        .catch(error => {
            console.error('Error rejecting version:', error);
            this.showNotification('Error rejecting version', 'error');
        });
    }

    approveVersion() {
        const versionId = this.selectedVersions.original || this.selectedVersions.modified;
        if (!versionId) {
            this.showNotification('Please select a version to approve', 'error');
            return;
        }

        this.approveVersionById(versionId);
    }

    rejectVersion() {
        const versionId = this.selectedVersions.original || this.selectedVersions.modified;
        if (!versionId) {
            this.showNotification('Please select a version to reject', 'error');
            return;
        }

        this.rejectVersionById(versionId);
    }

    getVersionStatusBadge(status) {
        const statusConfig = {
            'draft': { class: 'badge-secondary', text: 'Draft' },
            'pending': { class: 'badge-warning', text: 'Pending' },
            'approved': { class: 'badge-success', text: 'Approved' },
            'rejected': { class: 'badge-danger', text: 'Rejected' },
            'deployed': { class: 'badge-info', text: 'Deployed' }
        };

        const config = statusConfig[status] || statusConfig.draft;
        return `<span class="badge ${config.class}">${config.text}</span>`;
    }
}

// Export for global use
window.versionManager = new VersionManager();