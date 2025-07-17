@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Create Script</h1>
    <a href="{{ route('scripts.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        Back to Scripts
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Script Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('scripts.store') }}" id="scriptForm">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Script Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="language" class="form-label">Language</label>
                        <select class="form-select @error('language') is-invalid @enderror" id="language" name="language">
                            <option value="javascript" {{ old('language') == 'javascript' ? 'selected' : '' }}>JavaScript</option>
                        </select>
                        @error('language')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="tags" class="form-label">Tags</label>
                        <input type="text" class="form-control" id="tags" name="tags_input" value="{{ old('tags_input') }}" placeholder="Enter tags separated by commas">
                        <div class="form-text">Separate tags with commas (e.g., automation, webhook, integration)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Script Code <span class="text-danger">*</span></label>
                        <div class="script-editor-container">
                            <div class="editor-toolbar">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="formatCode()">
                                        <i class="fas fa-code"></i> Format
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="validateSyntax()">
                                        <i class="fas fa-check-circle"></i> Validate
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertTemplate()">
                                        <i class="fas fa-plus"></i> Template
                                    </button>
                                </div>
                                <div class="editor-status">
                                    <span id="editorStatus" class="text-muted">Ready</span>
                                </div>
                            </div>
                            <div id="codeEditor" class="code-editor"></div>
                            <textarea id="code" name="code" class="form-control d-none @error('code') is-invalid @enderror" required>{{ old('code') }}</textarea>
                        </div>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Use the API object to interact with the system:
                            <code>api.database.query()</code>, <code>api.http.get()</code>, <code>api.events.dispatch()</code>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                Active (script can be executed)
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" onclick="validateScript()">
                            <i class="fas fa-check"></i>
                            Validate
                        </button>
                        <div>
                            <button type="button" class="btn btn-outline-primary" onclick="saveAsDraft()">
                                <i class="fas fa-save"></i>
                                Save as Draft
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Create Script
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Script Templates -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Script Templates</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <button type="button" class="list-group-item list-group-item-action" onclick="loadTemplate('hello-world')">
                        <i class="fas fa-file-code"></i>
                        Hello World
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="loadTemplate('database-query')">
                        <i class="fas fa-database"></i>
                        Database Query
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="loadTemplate('http-request')">
                        <i class="fas fa-globe"></i>
                        HTTP Request
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="loadTemplate('event-dispatch')">
                        <i class="fas fa-paper-plane"></i>
                        Event Dispatch
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="loadTemplate('user-notification')">
                        <i class="fas fa-bell"></i>
                        User Notification
                    </button>
                </div>
            </div>
        </div>
        
        <!-- API Documentation -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">API Documentation</h6>
            </div>
            <div class="card-body">
                <div class="accordion" id="apiAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#databaseApi">
                                Database API
                            </button>
                        </h2>
                        <div id="databaseApi" class="accordion-collapse collapse" data-bs-parent="#apiAccordion">
                            <div class="accordion-body">
                                <small>
                                    <strong>api.database.query(sql, bindings)</strong><br>
                                    Execute SQL query with bindings<br><br>
                                    
                                    <strong>api.database.select(table, columns, conditions)</strong><br>
                                    Select records from table<br><br>
                                    
                                    <strong>api.database.insert(table, data)</strong><br>
                                    Insert record into table<br><br>
                                    
                                    <strong>api.database.update(table, data, conditions)</strong><br>
                                    Update records in table
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#httpApi">
                                HTTP API
                            </button>
                        </h2>
                        <div id="httpApi" class="accordion-collapse collapse" data-bs-parent="#apiAccordion">
                            <div class="accordion-body">
                                <small>
                                    <strong>api.http.get(url, headers)</strong><br>
                                    Make GET request<br><br>
                                    
                                    <strong>api.http.post(url, data, headers)</strong><br>
                                    Make POST request<br><br>
                                    
                                    <strong>api.http.put(url, data, headers)</strong><br>
                                    Make PUT request<br><br>
                                    
                                    <strong>api.http.delete(url, headers)</strong><br>
                                    Make DELETE request
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#eventApi">
                                Event API
                            </button>
                        </h2>
                        <div id="eventApi" class="accordion-collapse collapse" data-bs-parent="#apiAccordion">
                            <div class="accordion-body">
                                <small>
                                    <strong>api.events.dispatch(eventName, data)</strong><br>
                                    Dispatch application event<br><br>
                                    
                                    Example events:<br>
                                    - script.custom.notification<br>
                                    - user.status.changed<br>
                                    - order.completed
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#utilsApi">
                                Utility API
                            </button>
                        </h2>
                        <div id="utilsApi" class="accordion-collapse collapse" data-bs-parent="#apiAccordion">
                            <div class="accordion-body">
                                <small>
                                    <strong>api.utils.now()</strong><br>
                                    Get current timestamp<br><br>
                                    
                                    <strong>api.utils.uuid()</strong><br>
                                    Generate UUID<br><br>
                                    
                                    <strong>api.utils.hash(value)</strong><br>
                                    Hash string with SHA-256<br><br>
                                    
                                    <strong>api.utils.parseJson(json)</strong><br>
                                    Parse JSON string safely
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Validation Results -->
        <div class="card" id="validationResults" style="display: none;">
            <div class="card-header">
                <h6 class="card-title mb-0">Validation Results</h6>
            </div>
            <div class="card-body">
                <div id="validationContent"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let editor;

// Initialize CodeMirror
document.addEventListener('DOMContentLoaded', function() {
    editor = CodeMirror(document.getElementById('codeEditor'), {
        mode: 'javascript',
        theme: 'monokai',
        lineNumbers: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        indentUnit: 2,
        tabSize: 2,
        lineWrapping: true,
        foldGutter: true,
        gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
        keyMap: 'sublime',
        extraKeys: {
            'Ctrl-Space': 'autocomplete',
            'Ctrl-/': 'toggleComment',
            'Ctrl-F': 'findPersistent',
            'Ctrl-H': 'replace'
        }
    });

    // Sync editor content with textarea
    editor.on('change', function() {
        document.getElementById('code').value = editor.getValue();
        updateEditorStatus();
    });

    // Load initial content if any
    const initialCode = document.getElementById('code').value;
    if (initialCode) {
        editor.setValue(initialCode);
    }
    
    updateEditorStatus();
});

function updateEditorStatus() {
    const lines = editor.lineCount();
    const chars = editor.getValue().length;
    document.getElementById('editorStatus').textContent = `${lines} lines, ${chars} characters`;
}

function formatCode() {
    const code = editor.getValue();
    try {
        // Basic JavaScript formatting
        const formatted = code
            .replace(/\s*{\s*/g, ' {\n    ')
            .replace(/;\s*/g, ';\n    ')
            .replace(/\s*}\s*/g, '\n}\n');
        editor.setValue(formatted);
        document.getElementById('editorStatus').textContent = 'Code formatted';
    } catch (e) {
        document.getElementById('editorStatus').textContent = 'Format failed';
    }
}

function validateSyntax() {
    const code = editor.getValue();
    if (!code.trim()) {
        document.getElementById('editorStatus').textContent = 'No code to validate';
        return;
    }
    
    document.getElementById('editorStatus').textContent = 'Validating...';
    
    try {
        // Basic JavaScript syntax check
        new Function(code);
        document.getElementById('editorStatus').textContent = 'Syntax valid ✓';
    } catch (e) {
        document.getElementById('editorStatus').textContent = 'Syntax error: ' + e.message;
    }
}

function insertTemplate() {
    const templateCode = `// Template: Basic Script
const data = api.database.select('users', ['id', 'name'], { active: true });
const users = JSON.parse(data);

users.forEach(user => {
    api.log.info('Processing user: ' + user.name);
});

return { processed: users.length };`;
    
    editor.setValue(templateCode);
    document.getElementById('editorStatus').textContent = 'Template inserted';
}

// Script templates
const templates = {
    'hello-world': {
        name: 'Hello World',
        description: 'Simple hello world script',
        code: `// Hello World Script
const message = "Hello, World!";
api.log.info(message);
return message;`
    },
    'database-query': {
        name: 'Database Query',
        description: 'Query database for user data',
        code: `// Database Query Script
const users = api.database.select('users', ['id', 'name', 'email'], {
    active: true
});

const parsedUsers = JSON.parse(users);
api.log.info('Retrieved ' + parsedUsers.length + ' active users');

return {
    count: parsedUsers.length,
    users: parsedUsers
};`
    },
    'http-request': {
        name: 'HTTP Request',
        description: 'Make HTTP request to external API',
        code: `// HTTP Request Script
const response = api.http.get('https://jsonplaceholder.typicode.com/posts/1');
const data = JSON.parse(response);

api.log.info('Retrieved post: ' + data.title);

return {
    title: data.title,
    body: data.body,
    userId: data.userId
};`
    },
    'event-dispatch': {
        name: 'Event Dispatch',
        description: 'Dispatch custom application event',
        code: `// Event Dispatch Script
const eventData = {
    timestamp: api.utils.now(),
    message: 'Custom event triggered',
    scriptId: api.getScriptInfo().id
};

api.events.dispatch('script.custom.notification', eventData);
api.log.info('Event dispatched successfully');

return {
    event: 'script.custom.notification',
    data: eventData
};`
    },
    'user-notification': {
        name: 'User Notification',
        description: 'Send notification to users',
        code: `// User Notification Script
const users = api.database.select('users', ['id', 'name', 'email'], {
    active: true,
    notifications_enabled: true
});

const parsedUsers = JSON.parse(users);
let notificationCount = 0;

parsedUsers.forEach(user => {
    api.events.dispatch('user.notification', {
        user_id: user.id,
        title: 'System Notification',
        message: 'Hello ' + user.name + ', you have a new notification!',
        type: 'info'
    });
    notificationCount++;
});

api.log.info('Sent ' + notificationCount + ' notifications');

return {
    notificationsSent: notificationCount,
    users: parsedUsers.length
};`
    }
};

function loadTemplate(templateKey) {
    const template = templates[templateKey];
    if (template) {
        document.getElementById('name').value = template.name;
        document.getElementById('description').value = template.description;
        editor.setValue(template.code);
        editor.focus();
    }
}

function validateScript() {
    const code = editor.getValue();
    if (!code.trim()) {
        alert('Please enter script code first');
        return;
    }

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
    btn.disabled = true;

    fetch('/scripts/validate-syntax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ code: code })
    })
    .then(response => response.json())
    .then(data => {
        showValidationResults(data);
    })
    .catch(error => {
        alert('Validation failed: ' + error.message);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function showValidationResults(data) {
    const resultsDiv = document.getElementById('validationResults');
    const contentDiv = document.getElementById('validationContent');
    
    let html = '';
    
    // Syntax validation
    if (data.syntax_valid) {
        html += '<div class="alert alert-success"><i class="fas fa-check"></i> Syntax is valid</div>';
    } else {
        html += '<div class="alert alert-danger"><i class="fas fa-times"></i> Syntax error: ' + data.syntax_error;
        if (data.syntax_line) {
            html += ' (Line ' + data.syntax_line + ')';
        }
        html += '</div>';
    }
    
    // Security validation
    if (data.security_valid) {
        html += '<div class="alert alert-success"><i class="fas fa-shield-alt"></i> No security issues found</div>';
    } else {
        html += '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Security issues:</div>';
        html += '<ul>';
        data.security_issues.forEach(issue => {
            html += '<li>' + issue + '</li>';
        });
        html += '</ul>';
    }
    
    contentDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
}

function saveAsDraft() {
    document.getElementById('is_active').checked = false;
    document.getElementById('scriptForm').submit();
}

// Handle form submission
document.getElementById('scriptForm').addEventListener('submit', function(e) {
    // Process tags
    const tagsInput = document.getElementById('tags').value;
    if (tagsInput) {
        const tags = tagsInput.split(',').map(tag => tag.trim()).filter(tag => tag);
        
        // Add hidden input for tags array
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'tags';
        hiddenInput.value = JSON.stringify(tags);
        this.appendChild(hiddenInput);
    }
    
    // Ensure code is synced
    document.getElementById('code').value = editor.getValue();
});

// Auto-save functionality
let autoSaveTimeout;
editor.on('change', function() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(function() {
        // Auto-save to localStorage
        localStorage.setItem('script_draft', JSON.stringify({
            name: document.getElementById('name').value,
            description: document.getElementById('description').value,
            code: editor.getValue(),
            tags: document.getElementById('tags').value
        }));
    }, 2000);
});

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    const draft = localStorage.getItem('script_draft');
    if (draft && !document.getElementById('name').value) {
        try {
            const draftData = JSON.parse(draft);
            if (confirm('Load saved draft?')) {
                document.getElementById('name').value = draftData.name || '';
                document.getElementById('description').value = draftData.description || '';
                document.getElementById('tags').value = draftData.tags || '';
                if (editor && draftData.code) {
                    editor.setValue(draftData.code);
                }
            }
        } catch (e) {
            localStorage.removeItem('script_draft');
        }
    }
});

// Clear draft on successful submission
window.addEventListener('beforeunload', function() {
    if (document.getElementById('scriptForm').checkValidity()) {
        localStorage.removeItem('script_draft');
    }
});
</script>
@endpush