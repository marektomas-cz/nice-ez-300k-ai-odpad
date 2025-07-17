<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'NICE Scripting Solution') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/lint/lint.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/dialog/dialog.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/matchesonscrollbar.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom Styles -->
    <style>
        .CodeMirror {
            height: 400px;
            font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
            font-size: 14px;
        }
        
        .script-editor {
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            overflow: hidden;
        }
        
        .execution-log {
            max-height: 300px;
            overflow-y: auto;
            background: #1f2937;
            color: #f9fafb;
            font-family: 'Consolas', monospace;
            font-size: 12px;
            padding: 1rem;
            border-radius: 0.375rem;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1;
        }
        
        .status-success {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .status-failed {
            background-color: #fef2f2;
            color: #b91c1c;
        }
        
        .status-running {
            background-color: #fef3c7;
            color: #a16207;
        }
        
        .status-pending {
            background-color: #f3f4f6;
            color: #6b7280;
        }
        
        .security-score {
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .security-low {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .security-medium {
            background-color: #fef3c7;
            color: #a16207;
        }
        
        .security-high {
            background-color: #fed7aa;
            color: #c2410c;
        }
        
        .security-critical {
            background-color: #fecaca;
            color: #dc2626;
        }
        
        .stats-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stats-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .btn-primary {
            background-color: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }
        
        .btn-secondary {
            background-color: #6b7280;
            border-color: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
            border-color: #4b5563;
        }
        
        .btn-danger {
            background-color: #ef4444;
            border-color: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        
        .btn-success {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
        }
        
        .navbar {
            background-color: #1f2937;
            border-bottom: 1px solid #374151;
        }
        
        .navbar-brand {
            color: white;
            font-weight: 600;
        }
        
        .navbar-nav .nav-link {
            color: #d1d5db;
        }
        
        .navbar-nav .nav-link:hover {
            color: white;
        }
        
        .sidebar {
            background-color: #f9fafb;
            border-right: 1px solid #e5e7eb;
            height: calc(100vh - 64px);
            overflow-y: auto;
        }
        
        .sidebar-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .sidebar-item:hover {
            background-color: #f3f4f6;
        }
        
        .sidebar-item.active {
            background-color: #dbeafe;
            border-right: 3px solid #3b82f6;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .pagination {
            margin: 0;
        }
        
        .pagination .page-link {
            color: #6b7280;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }
        
        .alert {
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }
        
        .alert-danger {
            background-color: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }
        
        .alert-warning {
            background-color: #fffbeb;
            border-color: #fed7aa;
            color: #a16207;
        }
        
        .alert-info {
            background-color: #f0f9ff;
            border-color: #bae6fd;
            color: #0369a1;
        }
    </style>
</head>

<body class="font-sans antialiased">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="fas fa-code"></i>
                NICE Scripting Solution
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('scripts.index') }}">
                            <i class="fas fa-code"></i>
                            Scripts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('executions.index') }}">
                            <i class="fas fa-play"></i>
                            Executions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('monitoring.index') }}">
                            <i class="fas fa-chart-line"></i>
                            Monitoring
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                            {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('profile.show') }}">Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('settings.index') }}">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Logout</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                @yield('sidebar')
            </div>
            
            <!-- Content Area -->
            <div class="col-md-9 col-lg-10 p-4">
                <!-- Flash Messages -->
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                @yield('content')
            </div>
        </div>
    </div>

    <!-- CodeMirror JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/lint/lint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/lint/javascript-lint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/foldcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/brace-fold.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/dialog/dialog.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/comment/comment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/keymap/sublime.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // CSRF token setup for AJAX requests
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    </script>
    
    @stack('scripts')
</body>
</html>