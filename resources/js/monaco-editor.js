// Monaco Editor integration for script editing
import * as monaco from 'monaco-editor';
import { language as javascriptLanguage } from 'monaco-editor/esm/vs/basic-languages/javascript/javascript';

// Configure Monaco Editor environment
self.MonacoEnvironment = {
    getWorkerUrl: function (moduleId, label) {
        if (label === 'json') {
            return './vs/language/json/json.worker.js';
        }
        if (label === 'css' || label === 'scss' || label === 'less') {
            return './vs/language/css/css.worker.js';
        }
        if (label === 'html' || label === 'handlebars' || label === 'razor') {
            return './vs/language/html/html.worker.js';
        }
        if (label === 'typescript' || label === 'javascript') {
            return './vs/language/typescript/ts.worker.js';
        }
        return './vs/editor/editor.worker.js';
    }
};

class ScriptEditor {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.editor = null;
        this.options = {
            theme: 'vs-dark',
            language: 'javascript',
            automaticLayout: true,
            minimap: { enabled: true },
            lineNumbers: 'on',
            scrollBeyondLastLine: false,
            fontSize: 14,
            tabSize: 2,
            wordWrap: 'on',
            formatOnPaste: true,
            formatOnType: true,
            suggestOnTriggerCharacters: true,
            quickSuggestions: {
                other: true,
                comments: true,
                strings: true
            },
            ...options
        };
        
        this.init();
    }

    init() {
        // Configure JavaScript language settings
        monaco.languages.typescript.javascriptDefaults.setDiagnosticsOptions({
            noSemanticValidation: false,
            noSyntaxValidation: false,
        });

        // Configure compiler options
        monaco.languages.typescript.javascriptDefaults.setCompilerOptions({
            target: monaco.languages.typescript.ScriptTarget.ES2020,
            allowNonTsExtensions: true,
            moduleResolution: monaco.languages.typescript.ModuleResolutionKind.NodeJs,
            module: monaco.languages.typescript.ModuleKind.CommonJS,
            allowJs: true,
            checkJs: true
        });

        // Add custom API definitions
        this.addAPIDefinitions();

        // Create editor instance
        this.editor = monaco.editor.create(this.container, this.options);

        // Add custom commands
        this.addCustomCommands();

        // Setup event handlers
        this.setupEventHandlers();
    }

    addAPIDefinitions() {
        const apiDefinitions = `
declare global {
    const api: {
        log: {
            info(message: string): void;
            error(message: string): void;
            warn(message: string): void;
            debug(message: string): void;
        };
        utils: {
            now(): number;
            uuid(): string;
            hash(data: string): Promise<string>;
            parseJson(json: string): any;
        };
        database: {
            query(sql: string, bindings?: any[]): Promise<any>;
            select(table: string, columns: string[], conditions?: any): Promise<any>;
            insert(table: string, data: any): Promise<any>;
            update(table: string, data: any, conditions: any): Promise<any>;
            delete(table: string, conditions: any): Promise<any>;
        };
        http: {
            get(url: string, headers?: any): Promise<any>;
            post(url: string, data?: any, headers?: any): Promise<any>;
            put(url: string, data?: any, headers?: any): Promise<any>;
            patch(url: string, data?: any, headers?: any): Promise<any>;
            delete(url: string, headers?: any): Promise<any>;
        };
        events: {
            dispatch(eventName: string, data: any): Promise<void>;
        };
        getScriptInfo(): {
            id: string;
            client_id: string;
            execution_id: string;
        };
    };
}
`;

        monaco.languages.typescript.javascriptDefaults.addExtraLib(
            apiDefinitions,
            'ts:filename/api.d.ts'
        );
    }

    addCustomCommands() {
        // Format document
        this.editor.addAction({
            id: 'format-document',
            label: 'Format Document',
            keybindings: [
                monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyF
            ],
            precondition: null,
            keybindingContext: null,
            contextMenuGroupId: 'navigation',
            contextMenuOrder: 1.5,
            run: (ed) => {
                ed.getAction('editor.action.formatDocument').run();
            }
        });

        // Insert template
        this.editor.addAction({
            id: 'insert-template',
            label: 'Insert Template',
            keybindings: [
                monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyT
            ],
            run: (ed) => {
                this.showTemplateMenu();
            }
        });
    }

    setupEventHandlers() {
        // Update status on change
        this.editor.onDidChangeModelContent(() => {
            const content = this.getValue();
            const lines = content.split('\n').length;
            const chars = content.length;
            this.updateStatus(`${lines} lines, ${chars} characters`);
            
            // Trigger validation
            this.validateSyntax();
        });

        // Handle cursor position changes
        this.editor.onDidChangeCursorPosition((e) => {
            const position = e.position;
            this.updateCursorPosition(position.lineNumber, position.column);
        });
    }

    getValue() {
        return this.editor.getValue();
    }

    setValue(value) {
        this.editor.setValue(value);
    }

    getSelection() {
        return this.editor.getModel().getValueInRange(this.editor.getSelection());
    }

    insertAtCursor(text) {
        const position = this.editor.getPosition();
        const range = new monaco.Range(
            position.lineNumber,
            position.column,
            position.lineNumber,
            position.column
        );
        
        this.editor.executeEdits('insert', [{
            range: range,
            text: text,
            forceMoveMarkers: true
        }]);
    }

    async validateSyntax() {
        const code = this.getValue();
        if (!code.trim()) return;

        try {
            // Get Monaco's built-in diagnostics
            const model = this.editor.getModel();
            const markers = monaco.editor.getModelMarkers({ resource: model.uri });
            
            if (markers.length > 0) {
                const errors = markers.map(marker => ({
                    line: marker.startLineNumber,
                    column: marker.startColumn,
                    message: marker.message,
                    severity: marker.severity
                }));
                
                this.updateStatus(`Syntax errors found: ${errors.length}`);
                return { valid: false, errors };
            }
            
            this.updateStatus('Syntax valid ✓');
            return { valid: true };
            
        } catch (error) {
            this.updateStatus('Validation error');
            return { valid: false, error: error.message };
        }
    }

    showTemplateMenu() {
        // This would show a template selection dialog
        // For now, insert a basic template
        const template = `// Script Template
const data = await api.database.select('users', ['id', 'name'], { active: true });
const users = JSON.parse(data);

for (const user of users) {
    api.log.info(\`Processing user: \${user.name}\`);
}

return { processed: users.length };`;

        this.setValue(template);
    }

    updateStatus(message) {
        const statusElement = document.getElementById('editorStatus');
        if (statusElement) {
            statusElement.textContent = message;
        }
    }

    updateCursorPosition(line, column) {
        const positionElement = document.getElementById('editorPosition');
        if (positionElement) {
            positionElement.textContent = `Line ${line}, Column ${column}`;
        }
    }

    setTheme(theme) {
        monaco.editor.setTheme(theme);
    }

    setLanguage(language) {
        monaco.editor.setModelLanguage(this.editor.getModel(), language);
    }

    dispose() {
        this.editor.dispose();
    }

    // Utility methods for working with versions
    diffWithVersion(originalCode, modifiedCode) {
        const originalModel = monaco.editor.createModel(originalCode, 'javascript');
        const modifiedModel = monaco.editor.createModel(modifiedCode, 'javascript');
        
        const diffEditor = monaco.editor.createDiffEditor(this.container, {
            enableSplitViewResizing: false,
            renderSideBySide: true,
            ...this.options
        });
        
        diffEditor.setModel({
            original: originalModel,
            modified: modifiedModel
        });
        
        return diffEditor;
    }
}

// Export for use in other modules
window.ScriptEditor = ScriptEditor;

export default ScriptEditor;