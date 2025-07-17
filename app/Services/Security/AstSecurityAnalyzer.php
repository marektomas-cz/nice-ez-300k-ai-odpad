<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AstSecurityAnalyzer
{
    private array $config;
    private array $whitelist;
    private array $blacklist;
    private array $riskPatterns;

    public function __construct()
    {
        $this->config = config('scripting.security.ast', []);
        $this->initializePatterns();
    }

    /**
     * Initialize security patterns
     */
    private function initializePatterns(): void
    {
        $this->whitelist = [
            // Safe API functions
            'functions' => [
                'api.database.query',
                'api.database.select',
                'api.database.insert',
                'api.database.update',
                'api.http.get',
                'api.http.post',
                'api.http.put',
                'api.http.delete',
                'api.events.dispatch',
                'api.utils.now',
                'api.utils.uuid',
                'api.utils.hash',
                'api.utils.parseJson',
                'api.log.info',
                'api.log.error',
                'api.log.warn',
                'api.log.debug',
                'JSON.parse',
                'JSON.stringify',
                'Date.now',
                'Math.floor',
                'Math.ceil',
                'Math.round',
                'Math.max',
                'Math.min',
                'parseInt',
                'parseFloat',
                'String.prototype.slice',
                'String.prototype.substring',
                'String.prototype.indexOf',
                'String.prototype.includes',
                'String.prototype.startsWith',
                'String.prototype.endsWith',
                'String.prototype.trim',
                'String.prototype.toLowerCase',
                'String.prototype.toUpperCase',
                'Array.prototype.map',
                'Array.prototype.filter',
                'Array.prototype.reduce',
                'Array.prototype.forEach',
                'Array.prototype.find',
                'Array.prototype.includes',
                'Array.prototype.push',
                'Array.prototype.pop',
                'Array.prototype.shift',
                'Array.prototype.unshift',
                'Array.prototype.splice',
                'Array.prototype.slice',
                'Array.prototype.sort',
                'Array.prototype.reverse',
                'Array.prototype.join',
                'Object.keys',
                'Object.values',
                'Object.entries',
                'Object.assign',
                'console.log',
                'console.error',
                'console.warn',
                'console.info',
            ],
            // Safe operators
            'operators' => [
                '+', '-', '*', '/', '%',
                '==', '!=', '===', '!==',
                '<', '>', '<=', '>=',
                '&&', '||', '!',
                '&', '|', '^', '~', '<<', '>>',
                '?', ':',
                '=', '+=', '-=', '*=', '/=', '%=',
                'typeof', 'instanceof',
                'in', 'of',
            ],
            // Safe keywords
            'keywords' => [
                'var', 'let', 'const',
                'if', 'else', 'for', 'while', 'do',
                'switch', 'case', 'default', 'break', 'continue',
                'return', 'throw', 'try', 'catch', 'finally',
                'function', 'async', 'await',
                'true', 'false', 'null', 'undefined',
                'this', 'new',
            ],
        ];

        $this->blacklist = [
            // Dangerous functions
            'functions' => [
                'eval',
                'Function',
                'setTimeout',
                'setInterval',
                'setImmediate',
                'require',
                'import',
                'importScripts',
                'XMLHttpRequest',
                'fetch',
                'WebSocket',
                'Worker',
                'SharedWorker',
                'ServiceWorker',
                'navigator',
                'location',
                'history',
                'document',
                'window',
                'global',
                'globalThis',
                'process',
                '__dirname',
                '__filename',
                'Buffer',
                'fs',
                'path',
                'os',
                'crypto',
                'child_process',
                'cluster',
                'http',
                'https',
                'net',
                'tls',
                'url',
                'querystring',
                'stream',
                'util',
                'vm',
                'repl',
                'readline',
                'events',
                'assert',
                'zlib',
                'string_decoder',
                'punycode',
                'dns',
                'tty',
                'dgram',
                'inspector',
                'worker_threads',
                'perf_hooks',
                'async_hooks',
                'trace_events',
                'v8',
                'wasi',
            ],
            // Dangerous patterns
            'patterns' => [
                '/\b(eval|Function)\s*\(/i',
                '/\b(setTimeout|setInterval)\s*\(/i',
                '/\b(require|import)\s*\(/i',
                '/\b(XMLHttpRequest|fetch|WebSocket)\s*\(/i',
                '/\b(document|window|global|globalThis|process)\s*\./i',
                '/\b(__dirname|__filename|Buffer|fs|path|os|crypto)\b/i',
                '/\bchild_process\b/i',
                '/\bcluster\b/i',
                '/\b(http|https|net|tls)\b/i',
                '/\.constructor\s*\(/i',
                '/\[\s*[\'"]constructor[\'"]\s*\]/i',
                '/\bthis\s*\[\s*[\'"]constructor[\'"]\s*\]/i',
                '/\bwith\s*\(/i',
                '/\bdelete\s+\w+\s*\[/i',
                '/\bvoid\s*\(/i',
                '/\b0x[0-9a-f]+/i',
                '/\\\\/i',
                '/\x00/i',
                '/javascript:/i',
                '/vbscript:/i',
                '/data:/i',
                '/file:/i',
                '/ftp:/i',
                '/ldap:/i',
                '/gopher:/i',
                '/telnet:/i',
                '/dict:/i',
                '/imap:/i',
                '/pop:/i',
                '/smtp:/i',
                '/news:/i',
                '/nntp:/i',
                '/prospero:/i',
                '/wais:/i',
                '/ldaps:/i',
                '/mailto:/i',
                '/jar:/i',
                '/jnlp:/i',
                '/about:/i',
                '/chrome:/i',
                '/chrome-extension:/i',
                '/moz-extension:/i',
                '/resource:/i',
                '/view-source:/i',
                '/wyciwyg:/i',
                '/x-jsd:/i',
                '/x-javascript:/i',
                '/livescript:/i',
                '/mocha:/i',
                '/tcl:/i',
                '/python:/i',
                '/php:/i',
                '/perl:/i',
                '/ruby:/i',
                '/shell:/i',
                '/bash:/i',
                '/zsh:/i',
                '/fish:/i',
                '/csh:/i',
                '/ksh:/i',
                '/tcsh:/i',
                '/sh:/i',
                '/cmd:/i',
                '/powershell:/i',
                '/wscript:/i',
                '/cscript:/i',
                '/mshta:/i',
                '/rundll32:/i',
                '/regsvr32:/i',
                '/msiexec:/i',
                '/bitsadmin:/i',
                '/certutil:/i',
                '/schtasks:/i',
                '/wmic:/i',
                '/netsh:/i',
                '/reg:/i',
                '/sc:/i',
                '/taskkill:/i',
                '/tasklist:/i',
                '/net:/i',
                '/nslookup:/i',
                '/ping:/i',
                '/tracert:/i',
                '/telnet:/i',
                '/ftp:/i',
                '/tftp:/i',
                '/rcp:/i',
                '/rsh:/i',
                '/ssh:/i',
                '/scp:/i',
                '/sftp:/i',
                '/rsync:/i',
                '/wget:/i',
                '/curl:/i',
                '/lynx:/i',
                '/w3m:/i',
                '/links:/i',
                '/elinks:/i',
                '/nc:/i',
                '/netcat:/i',
                '/socat:/i',
                '/ncat:/i',
                '/openssl:/i',
                '/gpg:/i',
                '/pgp:/i',
                '/base64:/i',
                '/uuencode:/i',
                '/uudecode:/i',
                '/hexdump:/i',
                '/xxd:/i',
                '/od:/i',
                '/strings:/i',
                '/file:/i',
                '/which:/i',
                '/whereis:/i',
                '/locate:/i',
                '/find:/i',
                '/grep:/i',
                '/sed:/i',
                '/awk:/i',
                '/sort:/i',
                '/uniq:/i',
                '/cut:/i',
                '/tr:/i',
                '/head:/i',
                '/tail:/i',
                '/less:/i',
                '/more:/i',
                '/cat:/i',
                '/tac:/i',
                '/rev:/i',
                '/wc:/i',
                '/diff:/i',
                '/cmp:/i',
                '/comm:/i',
                '/join:/i',
                '/split:/i',
                '/csplit:/i',
                '/expand:/i',
                '/unexpand:/i',
                '/fmt:/i',
                '/fold:/i',
                '/nl:/i',
                '/pr:/i',
                '/paste:/i',
                '/column:/i',
                '/colrm:/i',
                '/tabs:/i',
                '/tee:/i',
                '/xargs:/i',
                '/parallel:/i',
                '/timeout:/i',
                '/gtimeout:/i',
                '/sleep:/i',
                '/usleep:/i',
                '/nanosleep:/i',
                '/watch:/i',
                '/yes:/i',
                '/true:/i',
                '/false:/i',
                '/nohup:/i',
                '/disown:/i',
                '/jobs:/i',
                '/bg:/i',
                '/fg:/i',
                '/kill:/i',
                '/killall:/i',
                '/pkill:/i',
                '/pgrep:/i',
                '/pidof:/i',
                '/ps:/i',
                '/pstree:/i',
                '/top:/i',
                '/htop:/i',
                '/btop:/i',
                '/iotop:/i',
                '/iftop:/i',
                '/nethogs:/i',
                '/ss:/i',
                '/netstat:/i',
                '/lsof:/i',
                '/fuser:/i',
                '/who:/i',
                '/w:/i',
                '/users:/i',
                '/id:/i',
                '/whoami:/i',
                '/su:/i',
                '/sudo:/i',
                '/doas:/i',
                '/pbrun:/i',
                '/pfexec:/i',
                '/runas:/i',
                '/kinit:/i',
                '/klist:/i',
                '/kdestroy:/i',
                '/kswitch:/i',
                '/kadmin:/i',
                '/kpasswd:/i',
                '/krb5-config:/i',
                '/gss-client:/i',
                '/saslpasswd2:/i',
                '/sasldblistusers2:/i',
                '/testsaslauthd:/i',
                '/pluginviewer:/i',
                '/cyrusbdb2current:/i',
                '/imapd:/i',
                '/pop3d:/i',
                '/lmtpd:/i',
                '/smmapd:/i',
                '/fud:/i',
                '/notifyd:/i',
                '/idled:/i',
                '/backupd:/i',
                '/synclogd:/i',
                '/quota:/i',
                '/reconstruct:/i',
                '/mbpath:/i',
                '/mbexamine:/i',
                '/cyrdump:/i',
                '/undohash:/i',
                '/rehash:/i',
                '/dohash:/i',
                '/chk_cyrus:/i',
                '/cvt_cyrusdb:/i',
                '/cyrus:/i',
                '/deliver:/i',
                '/rmnews:/i',
                '/fetchnews:/i',
                '/cyr_expire:/i',
                '/squatter:/i',
                '/mbutil:/i',
                '/ipurge:/i',
                '/cyr_dbtool:/i',
                '/cyr_df:/i',
                '/cyr_info:/i',
                '/cyr_sequence:/i',
                '/cyr_userseen:/i',
                '/cyr_virusscan:/i',
                '/arbitron:/i',
                '/arbitronsort:/i',
                '/cmmapd:/i',
                '/collectnews:/i',
                '/compile_et:/i',
                '/compile_st:/i',
                '/config2header:/i',
                '/config2man:/i',
                '/cyr_buildindex:/i',
                '/cyr_deny:/i',
                '/cyr_expire:/i',
                '/cyr_group:/i',
                '/cyr_ls:/i',
                '/cyr_paginate:/i',
                '/cyr_synclog:/i',
                '/cyr_timeout:/i',
                '/cyr_zip:/i',
                '/cyradm:/i',
                '/cyrdeliver:/i',
                '/cyrfetchnews:/i',
                '/cyrimport:/i',
                '/cyrmaster:/i',
                '/cyrreconstruct:/i',
                '/cyrrehash:/i',
                '/cyrquota:/i',
                '/cysyncrepl:/i',
                '/dav_reconstruct:/i',
                '/expire:/i',
                '/freebusy:/i',
                '/httpd:/i',
                '/imapd:/i',
                '/imtest:/i',
                '/installsieve:/i',
                '/lmtpd:/i',
                '/lmtptest:/i',
                '/master:/i',
                '/mbpath:/i',
                '/mupdatetest:/i',
                '/nntpd:/i',
                '/nntptest:/i',
                '/notifyd:/i',
                '/pop3d:/i',
                '/pop3test:/i',
                '/proxyd:/i',
                '/ptdump:/i',
                '/ptexpire:/i',
                '/ptloader:/i',
                '/reconstruct:/i',
                '/rmnews:/i',
                '/sievec:/i',
                '/sieved:/i',
                '/sivtest:/i',
                '/smtpd:/i',
                '/smtptest:/i',
                '/sync_client:/i',
                '/sync_reset:/i',
                '/sync_server:/i',
                '/timsieved:/i',
                '/tls_prune:/i',
                '/translatesieve:/i',
                '/unexpunge:/i',
                '/compile_et:/i',
                '/compile_st:/i',
                '/config2header:/i',
                '/config2man:/i',
                '/cyr_buildindex:/i',
                '/cyr_deny:/i',
                '/cyr_expire:/i',
                '/cyr_group:/i',
                '/cyr_ls:/i',
                '/cyr_paginate:/i',
                '/cyr_synclog:/i',
                '/cyr_timeout:/i',
                '/cyr_zip:/i',
                '/cyradm:/i',
                '/cyrdeliver:/i',
                '/cyrfetchnews:/i',
                '/cyrimport:/i',
                '/cyrmaster:/i',
                '/cyrreconstruct:/i',
                '/cyrrehash:/i',
                '/cyrquota:/i',
                '/cysyncrepl:/i',
                '/dav_reconstruct:/i',
                '/expire:/i',
                '/freebusy:/i',
                '/httpd:/i',
                '/imapd:/i',
                '/imtest:/i',
                '/installsieve:/i',
                '/lmtpd:/i',
                '/lmtptest:/i',
                '/master:/i',
                '/mbpath:/i',
                '/mupdatetest:/i',
                '/nntpd:/i',
                '/nntptest:/i',
                '/notifyd:/i',
                '/pop3d:/i',
                '/pop3test:/i',
                '/proxyd:/i',
                '/ptdump:/i',
                '/ptexpire:/i',
                '/ptloader:/i',
                '/reconstruct:/i',
                '/rmnews:/i',
                '/sievec:/i',
                '/sieved:/i',
                '/sivtest:/i',
                '/smtpd:/i',
                '/smtptest:/i',
                '/sync_client:/i',
                '/sync_reset:/i',
                '/sync_server:/i',
                '/timsieved:/i',
                '/tls_prune:/i',
                '/translatesieve:/i',
                '/unexpunge:/i',
            ],
        ];

        $this->riskPatterns = [
            'high' => [
                'eval_usage' => '/\beval\s*\(/i',
                'function_constructor' => '/\bFunction\s*\(/i',
                'timer_functions' => '/\b(setTimeout|setInterval)\s*\(/i',
                'require_usage' => '/\brequire\s*\(/i',
                'import_usage' => '/\bimport\s*\(/i',
                'global_access' => '/\b(global|globalThis|window|document|process)\s*\./i',
                'prototype_pollution' => '/\.__proto__\s*=/i',
                'constructor_access' => '/\.constructor\s*\(/i',
                'with_statement' => '/\bwith\s*\(/i',
                'delete_property' => '/\bdelete\s+\w+\s*\[/i',
                'void_operator' => '/\bvoid\s*\(/i',
                'hex_encoding' => '/\b0x[0-9a-f]+/i',
                'escape_sequences' => '/\\\\/i',
                'null_byte' => '/\x00/i',
                'protocol_handlers' => '/\b(javascript|vbscript|data|file):/i',
                'network_schemes' => '/\b(ftp|ldap|gopher|telnet|dict|imap|pop|smtp|news|nntp|prospero|wais|ldaps|mailto|jar|jnlp):/i',
                'browser_schemes' => '/\b(about|chrome|chrome-extension|moz-extension|resource|view-source|wyciwyg|x-jsd|x-javascript):/i',
                'script_languages' => '/\b(livescript|mocha|tcl|python|php|perl|ruby|shell|bash|zsh|fish|csh|ksh|tcsh|sh|cmd|powershell|wscript|cscript|mshta|rundll32|regsvr32|msiexec|bitsadmin|certutil|schtasks|wmic|netsh|reg|sc|taskkill|tasklist|net|nslookup|ping|tracert|telnet|ftp|tftp|rcp|rsh|ssh|scp|sftp|rsync|wget|curl|lynx|w3m|links|elinks|nc|netcat|socat|ncat|openssl|gpg|pgp|base64|uuencode|uudecode|hexdump|xxd|od|strings|file|which|whereis|locate|find|grep|sed|awk|sort|uniq|cut|tr|head|tail|less|more|cat|tac|rev|wc|diff|cmp|comm|join|split|csplit|expand|unexpand|fmt|fold|nl|pr|paste|column|colrm|tabs|tee|xargs|parallel|timeout|gtimeout|sleep|usleep|nanosleep|watch|yes|true|false|nohup|disown|jobs|bg|fg|kill|killall|pkill|pgrep|pidof|ps|pstree|top|htop|btop|iotop|iftop|nethogs|ss|netstat|lsof|fuser|who|w|users|id|whoami|su|sudo|doas|pbrun|pfexec|runas|kinit|klist|kdestroy|kswitch|kadmin|kpasswd|krb5-config|gss-client|saslpasswd2|sasldblistusers2|testsaslauthd|pluginviewer|cyrusbdb2current|imapd|pop3d|lmtpd|smmapd|fud|notifyd|idled|backupd|synclogd|quota|reconstruct|mbpath|mbexamine|cyrdump|undohash|rehash|dohash|chk_cyrus|cvt_cyrusdb|cyrus|deliver|rmnews|fetchnews|cyr_expire|squatter|mbutil|ipurge|cyr_dbtool|cyr_df|cyr_info|cyr_sequence|cyr_userseen|cyr_virusscan|arbitron|arbitronsort|cmmapd|collectnews|compile_et|compile_st|config2header|config2man|cyr_buildindex|cyr_deny|cyr_expire|cyr_group|cyr_ls|cyr_paginate|cyr_synclog|cyr_timeout|cyr_zip|cyradm|cyrdeliver|cyrfetchnews|cyrimport|cyrmaster|cyrreconstruct|cyrrehash|cyrquota|cysyncrepl|dav_reconstruct|expire|freebusy|httpd|imapd|imtest|installsieve|lmtpd|lmtptest|master|mbpath|mupdatetest|nntpd|nntptest|notifyd|pop3d|pop3test|proxyd|ptdump|ptexpire|ptloader|reconstruct|rmnews|sievec|sieved|sivtest|smtpd|smtptest|sync_client|sync_reset|sync_server|timsieved|tls_prune|translatesieve|unexpunge):/i',
            ],
            'medium' => [
                'obfuscation' => '/[\x00-\x1f\x7f-\x9f]/i',
                'encoding_tricks' => '/\\\\u[0-9a-f]{4}/i',
                'unicode_tricks' => '/\\\\x[0-9a-f]{2}/i',
                'octal_escape' => '/\\\\[0-7]{1,3}/i',
                'string_concat' => '/\+\s*[\'"]/i',
                'array_access' => '/\[\s*[\'"]/i',
                'computed_property' => '/\[\s*[a-zA-Z_$]/i',
                'nested_calls' => '/\(\s*\w+\s*\(\s*\w+\s*\(/i',
                'chained_calls' => '/\w+\s*\.\s*\w+\s*\.\s*\w+\s*\(/i',
                'complex_regex' => '/\/.*\{.*\}.*\//i',
                'long_strings' => '/[\'"][^\'"]{100,}[\'"]/',
                'base64_like' => '/[A-Za-z0-9+\/]{20,}={0,2}/i',
                'hex_strings' => '/[\'"][0-9a-f]{20,}[\'"]/',
                'suspicious_comments' => '/\/\*.*hack.*\*\//i',
            ],
            'low' => [
                'long_variable_names' => '/\b[a-zA-Z_$][a-zA-Z0-9_$]{50,}\b/',
                'deep_nesting' => '/\{\s*\{\s*\{\s*\{\s*\{/',
                'many_parameters' => '/function\s*\w*\s*\([^)]{100,}\)/',
                'complex_ternary' => '/\?\s*[^:]{50,}\s*:/i',
                'long_lines' => '/.{200,}/',
                'mixed_quotes' => '/[\'"][^\'"]*([\'"]).*/i',
                'trailing_spaces' => '/\s+$/m',
                'mixed_indentation' => '/^\t+ /m',
                'console_logs' => '/console\.(log|error|warn|info|debug)\s*\(/i',
                'todo_comments' => '/\/\/.*todo.*$/im',
            ],
        ];
    }

    /**
     * Analyze JavaScript code for security issues
     */
    public function analyze(string $code): array
    {
        $cacheKey = 'ast_security_analysis_' . md5($code);
        
        return Cache::remember($cacheKey, 300, function () use ($code) {
            $issues = [];
            $score = 100;
            
            // Check against blacklist patterns
            foreach ($this->blacklist['patterns'] as $pattern) {
                if (preg_match($pattern, $code)) {
                    $issues[] = [
                        'type' => 'blacklisted_pattern',
                        'severity' => 'high',
                        'message' => 'Code contains blacklisted pattern: ' . $pattern,
                        'line' => $this->findLineNumber($code, $pattern),
                        'pattern' => $pattern,
                    ];
                    $score -= 25;
                }
            }
            
            // Check against risk patterns
            foreach ($this->riskPatterns as $severity => $patterns) {
                foreach ($patterns as $name => $pattern) {
                    if (preg_match($pattern, $code)) {
                        $issues[] = [
                            'type' => 'risk_pattern',
                            'severity' => $severity,
                            'message' => 'Code contains risky pattern: ' . $name,
                            'line' => $this->findLineNumber($code, $pattern),
                            'pattern' => $pattern,
                            'risk_name' => $name,
                        ];
                        
                        $score -= match ($severity) {
                            'high' => 20,
                            'medium' => 10,
                            'low' => 5,
                            default => 5,
                        };
                    }
                }
            }
            
            // Check for unauthorized functions
            $unauthorizedFunctions = $this->findUnauthorizedFunctions($code);
            foreach ($unauthorizedFunctions as $func) {
                $issues[] = [
                    'type' => 'unauthorized_function',
                    'severity' => 'high',
                    'message' => 'Code uses unauthorized function: ' . $func['name'],
                    'line' => $func['line'],
                    'function' => $func['name'],
                ];
                $score -= 15;
            }
            
            // Check for complex code patterns
            $complexityIssues = $this->checkComplexity($code);
            foreach ($complexityIssues as $issue) {
                $issues[] = $issue;
                $score -= $issue['score_impact'] ?? 5;
            }
            
            // Check for encoding issues
            $encodingIssues = $this->checkEncoding($code);
            foreach ($encodingIssues as $issue) {
                $issues[] = $issue;
                $score -= $issue['score_impact'] ?? 10;
            }
            
            // Ensure score doesn't go below 0
            $score = max(0, $score);
            
            return [
                'issues' => $issues,
                'score' => $score,
                'risk_level' => $this->calculateRiskLevel($score),
                'summary' => $this->generateSummary($issues),
                'recommendations' => $this->generateRecommendations($issues),
                'analysis_time' => microtime(true),
            ];
        });
    }

    /**
     * Find unauthorized functions in code
     */
    private function findUnauthorizedFunctions(string $code): array
    {
        $unauthorizedFunctions = [];
        $lines = explode("\n", $code);
        
        foreach ($lines as $lineNumber => $line) {
            // Find function calls
            if (preg_match_all('/\b([a-zA-Z_$][a-zA-Z0-9_$]*(?:\.[a-zA-Z_$][a-zA-Z0-9_$]*)*)\s*\(/g', $line, $matches)) {
                foreach ($matches[1] as $functionName) {
                    if (!$this->isFunctionWhitelisted($functionName)) {
                        $unauthorizedFunctions[] = [
                            'name' => $functionName,
                            'line' => $lineNumber + 1,
                        ];
                    }
                }
            }
        }
        
        return $unauthorizedFunctions;
    }

    /**
     * Check if function is whitelisted
     */
    private function isFunctionWhitelisted(string $functionName): bool
    {
        return in_array($functionName, $this->whitelist['functions']) ||
               $this->isApiFunction($functionName) ||
               $this->isBuiltInFunction($functionName);
    }

    /**
     * Check if it's an API function
     */
    private function isApiFunction(string $functionName): bool
    {
        return str_starts_with($functionName, 'api.');
    }

    /**
     * Check if it's a built-in JavaScript function
     */
    private function isBuiltInFunction(string $functionName): bool
    {
        $builtInFunctions = [
            'Array', 'Object', 'String', 'Number', 'Boolean', 'Date', 'RegExp', 'Math', 'JSON',
            'isNaN', 'isFinite', 'parseInt', 'parseFloat', 'encodeURI', 'encodeURIComponent',
            'decodeURI', 'decodeURIComponent', 'escape', 'unescape',
        ];
        
        return in_array($functionName, $builtInFunctions);
    }

    /**
     * Check code complexity
     */
    private function checkComplexity(string $code): array
    {
        $issues = [];
        
        // Check cyclomatic complexity
        $complexity = $this->calculateCyclomaticComplexity($code);
        if ($complexity > 10) {
            $issues[] = [
                'type' => 'high_complexity',
                'severity' => 'medium',
                'message' => 'Code has high cyclomatic complexity: ' . $complexity,
                'line' => 1,
                'complexity' => $complexity,
                'score_impact' => min(20, $complexity - 10),
            ];
        }
        
        // Check nesting depth
        $nestingDepth = $this->calculateNestingDepth($code);
        if ($nestingDepth > 5) {
            $issues[] = [
                'type' => 'deep_nesting',
                'severity' => 'medium',
                'message' => 'Code has deep nesting: ' . $nestingDepth . ' levels',
                'line' => 1,
                'nesting_depth' => $nestingDepth,
                'score_impact' => min(15, $nestingDepth - 5),
            ];
        }
        
        // Check line length
        $lines = explode("\n", $code);
        foreach ($lines as $lineNumber => $line) {
            if (strlen($line) > 120) {
                $issues[] = [
                    'type' => 'long_line',
                    'severity' => 'low',
                    'message' => 'Line is too long: ' . strlen($line) . ' characters',
                    'line' => $lineNumber + 1,
                    'length' => strlen($line),
                    'score_impact' => 2,
                ];
            }
        }
        
        return $issues;
    }

    /**
     * Calculate cyclomatic complexity
     */
    private function calculateCyclomaticComplexity(string $code): int
    {
        $complexity = 1; // Base complexity
        
        // Count decision points
        $patterns = [
            '/\bif\s*\(/i',
            '/\belse\s+if\s*\(/i',
            '/\bwhile\s*\(/i',
            '/\bfor\s*\(/i',
            '/\bdo\s*\{/i',
            '/\bswitch\s*\(/i',
            '/\bcase\s+/i',
            '/\bcatch\s*\(/i',
            '/\?\s*[^:]*\s*:/i', // Ternary operator
            '/&&/i',
            '/\|\|/i',
        ];
        
        foreach ($patterns as $pattern) {
            $complexity += preg_match_all($pattern, $code);
        }
        
        return $complexity;
    }

    /**
     * Calculate nesting depth
     */
    private function calculateNestingDepth(string $code): int
    {
        $maxDepth = 0;
        $currentDepth = 0;
        
        for ($i = 0; $i < strlen($code); $i++) {
            if ($code[$i] === '{') {
                $currentDepth++;
                $maxDepth = max($maxDepth, $currentDepth);
            } elseif ($code[$i] === '}') {
                $currentDepth--;
            }
        }
        
        return $maxDepth;
    }

    /**
     * Check encoding issues
     */
    private function checkEncoding(string $code): array
    {
        $issues = [];
        
        // Check for non-ASCII characters
        if (!mb_check_encoding($code, 'ASCII')) {
            $issues[] = [
                'type' => 'non_ascii_characters',
                'severity' => 'medium',
                'message' => 'Code contains non-ASCII characters',
                'line' => 1,
                'score_impact' => 10,
            ];
        }
        
        // Check for unusual whitespace
        if (preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', $code)) {
            $issues[] = [
                'type' => 'unusual_whitespace',
                'severity' => 'high',
                'message' => 'Code contains unusual whitespace characters',
                'line' => 1,
                'score_impact' => 20,
            ];
        }
        
        // Check for potential obfuscation
        if (preg_match('/\\\\u[0-9a-f]{4}/i', $code)) {
            $issues[] = [
                'type' => 'unicode_escapes',
                'severity' => 'medium',
                'message' => 'Code contains Unicode escape sequences',
                'line' => $this->findLineNumber($code, '/\\\\u[0-9a-f]{4}/i'),
                'score_impact' => 15,
            ];
        }
        
        return $issues;
    }

    /**
     * Find line number for pattern match
     */
    private function findLineNumber(string $code, string $pattern): int
    {
        $lines = explode("\n", $code);
        
        foreach ($lines as $lineNumber => $line) {
            if (preg_match($pattern, $line)) {
                return $lineNumber + 1;
            }
        }
        
        return 1;
    }

    /**
     * Calculate risk level based on score
     */
    private function calculateRiskLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'low',
            $score >= 60 => 'medium',
            $score >= 40 => 'high',
            default => 'critical',
        };
    }

    /**
     * Generate summary
     */
    private function generateSummary(array $issues): array
    {
        $summary = [
            'total_issues' => count($issues),
            'by_severity' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'by_type' => [],
        ];
        
        foreach ($issues as $issue) {
            $severity = $issue['severity'];
            $type = $issue['type'];
            
            if (isset($summary['by_severity'][$severity])) {
                $summary['by_severity'][$severity]++;
            }
            
            if (!isset($summary['by_type'][$type])) {
                $summary['by_type'][$type] = 0;
            }
            $summary['by_type'][$type]++;
        }
        
        return $summary;
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations(array $issues): array
    {
        $recommendations = [];
        
        foreach ($issues as $issue) {
            $recommendation = $this->getRecommendationForIssue($issue);
            if ($recommendation && !in_array($recommendation, $recommendations)) {
                $recommendations[] = $recommendation;
            }
        }
        
        return $recommendations;
    }

    /**
     * Get recommendation for specific issue
     */
    private function getRecommendationForIssue(array $issue): ?string
    {
        return match ($issue['type']) {
            'blacklisted_pattern' => 'Remove or replace the blacklisted pattern with a safe alternative',
            'risk_pattern' => 'Review and mitigate the identified risk pattern',
            'unauthorized_function' => 'Use only whitelisted API functions',
            'high_complexity' => 'Refactor code to reduce complexity',
            'deep_nesting' => 'Reduce nesting depth by extracting functions',
            'long_line' => 'Break long lines into multiple shorter lines',
            'non_ascii_characters' => 'Use only ASCII characters in code',
            'unusual_whitespace' => 'Remove unusual whitespace characters',
            'unicode_escapes' => 'Avoid Unicode escape sequences',
            default => null,
        };
    }

    /**
     * Get whitelist information
     */
    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    /**
     * Get blacklist information
     */
    public function getBlacklist(): array
    {
        return $this->blacklist;
    }

    /**
     * Update whitelist
     */
    public function updateWhitelist(array $whitelist): void
    {
        $this->whitelist = array_merge_recursive($this->whitelist, $whitelist);
    }

    /**
     * Update blacklist
     */
    public function updateBlacklist(array $blacklist): void
    {
        $this->blacklist = array_merge_recursive($this->blacklist, $blacklist);
    }
}