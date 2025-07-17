import * as monaco from 'monaco-editor';

class ScriptEditor {
    constructor() {
        this.editor = null;
        this.currentScript = null;
        this.diffEditor = null;
        this.validationWorker = null;
    }

    init() {
        this.initializeEditor();
        this.initializeValidation();
        this.initializeDiffViewer();
        this.setupEventListeners();
    }

    initializeEditor() {
        const container = document.getElementById('script-editor');
        if (!container) return;

        // Configure Monaco for JavaScript/TypeScript
        monaco.languages.typescript.javascriptDefaults.setCompilerOptions({
            target: monaco.languages.typescript.ScriptTarget.ES2020,
            allowNonTsExtensions: true,
            moduleResolution: monaco.languages.typescript.ModuleResolutionKind.NodeJs,
            module: monaco.languages.typescript.ModuleKind.CommonJS,
            noEmit: true,
            esModuleInterop: true,
            jsx: monaco.languages.typescript.JsxEmit.React,
            allowJs: true,
            typeRoots: ["node_modules/@types"]
        });

        // Add custom API definitions for scripting environment
        monaco.languages.typescript.javascriptDefaults.addExtraLib(`
            declare namespace api {
                namespace database {
                    function query(sql: string, params?: any[]): Promise<any>;
                    function execute(sql: string, params?: any[]): Promise<number>;
                    function transaction(callback: () => Promise<void>): Promise<void>;
                }
                namespace events {
                    function dispatch(event: string, data?: any): void;
                    function listen(event: string, callback: (data: any) => void): void;
                }
                namespace http {
                    function get(url: string, options?: any): Promise<any>;
                    function post(url: string, data?: any, options?: any): Promise<any>;
                    function put(url: string, data?: any, options?: any): Promise<any>;
                    function delete(url: string, options?: any): Promise<any>;
                }
                namespace storage {
                    function get(key: string): Promise<any>;
                    function set(key: string, value: any): Promise<void>;
                    function delete(key: string): Promise<void>;
                }
                namespace log {
                    function info(message: string, data?: any): void;
                    function warn(message: string, data?: any): void;
                    function error(message: string, data?: any): void;
                }
            }
        `, 'api.d.ts');

        this.editor = monaco.editor.create(container, {
            value: container.dataset.initialValue || '// Start coding your script here\n',
            language: 'javascript',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: {
                enabled: true
            },
            fontSize: 14,
            wordWrap: 'on',
            lineNumbers: 'on',
            renderLineHighlight: 'all',
            contextmenu: true,
            folding: true,
            foldingStrategy: 'indentation',
            showFoldingControls: 'always',
            formatOnPaste: true,
            formatOnType: true,
            suggest: {
                snippetsPreventQuickSuggestions: false
            },
            quickSuggestions: {
                other: true,
                comments: true,
                strings: true
            },
            parameterHints: {
                enabled: true
            },
            autoIndent: 'advanced',
            bracketPairColorization: {
                enabled: true
            }
        });

        // Add custom snippets
        monaco.languages.registerCompletionItemProvider('javascript', {
            provideCompletionItems: (model, position) => {
                const suggestions = [
                    {
                        label: 'api.database.query',
                        kind: monaco.languages.CompletionItemKind.Function,
                        insertText: 'api.database.query(\'${1:SELECT * FROM table}\', [${2:}])',
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        documentation: 'Execute a database query'
                    },
                    {
                        label: 'api.events.dispatch',
                        kind: monaco.languages.CompletionItemKind.Function,
                        insertText: 'api.events.dispatch(\'${1:event.name}\', ${2:data})',
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        documentation: 'Dispatch an event'
                    },
                    {
                        label: 'api.http.get',
                        kind: monaco.languages.CompletionItemKind.Function,
                        insertText: 'api.http.get(\'${1:https://api.example.com/endpoint}\')',
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        documentation: 'Make HTTP GET request'
                    },
                    {
                        label: 'try-catch-template',
                        kind: monaco.languages.CompletionItemKind.Snippet,
                        insertText: [
                            'try {',
                            '    ${1:// Your code here}',
                            '} catch (error) {',
                            '    api.log.error(\'Error in script\', error);',
                            '    throw error;',
                            '}'
                        ].join('\n'),
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        documentation: 'Try-catch error handling template'
                    }
                ];
                return { suggestions };
            }
        });
    }

    initializeValidation() {
        // Real-time validation
        this.editor.onDidChangeModelContent(() => {
            this.validateScript();
        });
    }

    initializeDiffViewer() {
        const diffContainer = document.getElementById('script-diff');
        if (!diffContainer) return;

        this.diffEditor = monaco.editor.createDiffEditor(diffContainer, {
            theme: 'vs-dark',
            automaticLayout: true,
            renderSideBySide: true,
            readOnly: true
        });
    }

    setupEventListeners() {
        // Save button
        const saveButton = document.getElementById('save-script');
        if (saveButton) {
            saveButton.addEventListener('click', () => this.saveScript());
        }

        // Test button
        const testButton = document.getElementById('test-script');
        if (testButton) {
            testButton.addEventListener('click', () => this.testScript());
        }

        // Version dropdown
        const versionSelect = document.getElementById('version-select');
        if (versionSelect) {
            versionSelect.addEventListener('change', (e) => {
                this.loadVersion(e.target.value);
            });
        }

        // Format button
        const formatButton = document.getElementById('format-script');
        if (formatButton) {
            formatButton.addEventListener('click', () => {
                this.editor.trigger('format', 'editor.action.formatDocument');
            });
        }
    }

    validateScript() {
        if (!this.editor) return;

        const code = this.editor.getValue();
        
        // AST validation via API
        fetch('/api/scripts/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ code })
        })
        .then(response => response.json())
        .then(data => {
            if (data.errors && data.errors.length > 0) {
                const markers = data.errors.map(error => ({
                    severity: monaco.MarkerSeverity.Error,
                    startLineNumber: error.line,
                    startColumn: error.column,
                    endLineNumber: error.line,
                    endColumn: error.column + error.length,
                    message: error.message
                }));
                
                monaco.editor.setModelMarkers(this.editor.getModel(), 'validation', markers);
            } else {
                monaco.editor.setModelMarkers(this.editor.getModel(), 'validation', []);
            }
        })
        .catch(error => {
            console.error('Validation error:', error);
        });
    }

    saveScript() {
        if (!this.editor) return;

        const code = this.editor.getValue();
        const scriptId = document.getElementById('script-id')?.value;
        
        fetch(`/api/scripts/${scriptId || ''}`, {
            method: scriptId ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ 
                code,
                name: document.getElementById('script-name')?.value,
                description: document.getElementById('script-description')?.value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Script saved successfully', 'success');
                if (data.script_id) {
                    document.getElementById('script-id').value = data.script_id;
                }
            } else {
                this.showNotification(data.message || 'Error saving script', 'error');
            }
        })
        .catch(error => {
            console.error('Save error:', error);
            this.showNotification('Error saving script', 'error');
        });
    }

    testScript() {
        if (!this.editor) return;

        const code = this.editor.getValue();
        
        this.showNotification('Testing script...', 'info');
        
        fetch('/api/scripts/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ code })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showTestResults(data.result);
            } else {
                this.showNotification(data.message || 'Script test failed', 'error');
            }
        })
        .catch(error => {
            console.error('Test error:', error);
            this.showNotification('Error testing script', 'error');
        });
    }

    loadVersion(versionId) {
        fetch(`/api/scripts/versions/${versionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.editor.setValue(data.code);
                this.currentScript = data;
            }
        })
        .catch(error => {
            console.error('Version load error:', error);
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
    }

    showTestResults(result) {
        const resultsContainer = document.getElementById('test-results');
        if (!resultsContainer) return;

        resultsContainer.innerHTML = `
            <div class="test-results">
                <h4>Test Results</h4>
                <pre>${JSON.stringify(result, null, 2)}</pre>
            </div>
        `;
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
}

// Export for global use
window.ScriptEditor = new ScriptEditor();