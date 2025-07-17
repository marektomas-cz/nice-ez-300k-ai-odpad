import * as monaco from 'monaco-editor';
import './script-editor';

// Initialize Monaco editor for script editing
window.monaco = monaco;

// Global initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize script editor on pages that have it
    if (document.getElementById('script-editor')) {
        window.ScriptEditor.init();
    }
    
    // Initialize script metrics dashboard
    if (document.getElementById('metrics-dashboard')) {
        import('./metrics-dashboard').then(module => {
            module.MetricsDashboard.init();
        });
    }
    
    // Initialize script version manager
    if (document.getElementById('version-manager')) {
        import('./version-manager').then(module => {
            module.VersionManager.init();
        });
    }
});