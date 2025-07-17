# NICE Scripting Solution

## Enterprise-grade In-App Scripting Solution with Deno Sidecar Architecture

This project implements a secure, scalable in-app scripting solution for multi-tenant Laravel applications, designed to meet enterprise security and performance standards. The solution has been enhanced with a Deno sidecar architecture for improved security and performance.

## Architecture Overview

The solution follows a layered architecture with strict security boundaries:

- **Client Interface Layer**: User-friendly script management with syntax highlighting
- **Security Layer**: Enhanced AST-based security analysis, permission validation, and rate limiting
- **Execution Engine**: Secure Deno sidecar for isolated script execution
- **Monitoring Layer**: Comprehensive Prometheus metrics with kill-switch capabilities
- **Data Layer**: Comprehensive logging and audit trails

## Key Features

### 🔒 Security-First Design
- **AST Security Analysis**: Advanced Abstract Syntax Tree based code analysis using Peast parser
- **Deno Sidecar Isolation**: Scripts run in isolated Deno containers with strict security boundaries
- **Resource Limits**: CPU, memory, and execution time constraints with cgroups enforcement
- **Watchdog Service**: Real-time monitoring and automatic termination of runaway scripts
- **Kill-Switch System**: Emergency shutdown mechanism for security breaches and resource violations
- **API Gateway**: Controlled access to database and external services
- **Role-Based Access Control**: Fine-grained permissions using Spatie Laravel Permission
- **Secret Management**: Encrypted storage and rotation of API keys and credentials
- **Audit Logging**: Complete execution history and security events

### 🚀 Performance & Scalability
- **Async Execution**: Non-blocking script execution
- **Resource Monitoring**: Real-time performance metrics
- **Queue Integration**: Background processing for heavy tasks
- **Caching Layer**: Optimized script compilation and data access
- **Container Orchestration**: Docker-based deployment with resource limits
- **CI/CD Pipeline**: Automated testing, security scanning, and deployment

### 🎯 Developer Experience
- **Monaco Editor**: Advanced code editor with IntelliSense, auto-completion, and TypeScript support
- **Script Versioning**: Complete version control with diff visualization and rollback capabilities
- **Approval Workflow**: Multi-stage approval process for script deployment with role-based permissions
- **Real-time Validation**: Instant syntax and security validation with AST analysis
- **Script Templates**: Pre-built templates for common use cases
- **Error Handling**: Comprehensive error reporting and debugging
- **Testing Suite**: Unit, integration, and security tests
- **Documentation**: Complete API documentation and examples
- **Prometheus Monitoring**: Real-time metrics collection and visualization

## Installation

### Prerequisites
- PHP 8.1+
- Composer
- Laravel 10.x
- Redis (recommended for production)
- Docker & Docker Compose (for containerized deployment)
- Deno 1.40+ (for script execution runtime)
- Node.js 18+ (for frontend build)
- npm or yarn (package manager)
- Prometheus (for metrics collection)
- Grafana (for metrics visualization)

### Quick Start (Development)
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations and seed demo data
php artisan migrate
php artisan db:seed --class=ScriptingSeeder

# Build frontend assets
npm run dev

# Start development server
php artisan serve
```

### Docker Development Setup
```bash
# Build and start containers
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Build frontend assets
docker-compose exec app npm run build

# Run migrations
docker-compose exec app php artisan migrate

# Access application
open http://localhost:8080
```

### Production Setup
See [DEPLOYMENT.md](DEPLOYMENT.md) for complete production deployment instructions.

## Configuration

Configure the scripting environment in `config/scripting.php`:

```php
'execution' => [
    'timeout' => 30,        // Max execution time in seconds
    'memory_limit' => 32,   // Max memory in MB
    'rate_limit' => 100,    // Requests per minute
],
```

## Deno Sidecar Architecture

The solution now uses a secure Deno sidecar for script execution, replacing the previous V8Js integration:

### Architecture Benefits
- **Security Isolation**: Scripts run in separate containers with resource limits
- **Resource Control**: CPU and memory constraints prevent resource exhaustion
- **Modern Runtime**: TypeScript/JavaScript execution with Deno's secure-by-default approach
- **HTTP Communication**: Clean API between Laravel and Deno executor
- **Scalability**: Independent scaling of execution environment

### Deno Executor Features
- **Sandboxed Execution**: No file system or network access by default
- **Resource Limits**: Configurable memory and CPU constraints with cgroups enforcement
- **Health Monitoring**: Built-in health checks and metrics
- **Error Handling**: Comprehensive error reporting and timeout management
- **Security**: Runs as non-root user with minimal privileges
- **Watchdog Integration**: Real-time monitoring and automatic termination of runaway scripts
- **Kill-Switch Support**: Emergency shutdown mechanism for security breaches

### Script Execution Flow
1. **Request**: Laravel receives script execution request
2. **Security Analysis**: AST-based security validation with whitelist/blacklist patterns
3. **Watchdog Initialization**: Real-time monitoring setup with resource limits
4. **Sidecar Communication**: HTTP request to Deno executor with security context
5. **Execution**: Secure script execution in Deno runtime with isolation
6. **Monitoring**: Continuous resource monitoring and violation detection
7. **Response**: Results returned to Laravel application
8. **Metrics**: Prometheus metrics collection and alerting

## Usage

### Creating Scripts

```javascript
// Example client script
const result = api.database.query('SELECT * FROM users WHERE active = ?', [true]);
const users = JSON.parse(result);

users.forEach(user => {
    if (user.last_login < Date.now() - 86400000) {
        api.events.dispatch('user.inactive', { user_id: user.id });
    }
});

return { processed: users.length };
```

### Execution Triggers

- **Manual**: Via web interface or API
- **Event-driven**: Laravel event listeners
- **Scheduled**: Cron jobs and queued tasks
- **Sidecar Execution**: Secure execution via Deno container

## Script Versioning

The solution includes comprehensive version control for scripts with enhanced workflow features:

### Version Management
- **Semantic Versioning**: Automatic version numbering (major.minor.patch)
- **Change Tracking**: Complete diff calculation between versions with Monaco diff editor
- **Version Metadata**: Creator, timestamps, and change notes
- **Rollback Capability**: Easy rollback to previous versions with confirmation
- **Approval Workflow**: Multi-stage approval process for script deployment
- **Role-Based Permissions**: Approver roles for version control governance

### Usage Examples

```php
// Create a new version
$version = $script->createVersion('Fixed performance issue', auth()->user());

// Submit version for approval
$version->submitForApproval(auth()->user());

// Approve version (requires approver role)
$version->approve(auth()->user(), 'Reviewed and approved');

// Reject version with reason
$version->reject(auth()->user(), 'Security concerns identified');

// Rollback to specific version
$script->rollbackToVersion($version->id);

// Compare versions with diff visualization
$diff = $version->getDiff($previousVersion);

// Get version history with approval status
$versions = $script->versions()->with('approvals')->orderBy('created_at', 'desc')->get();
```

## Security Features

### Authentication & Authorization
- **Role-Based Access Control**: Comprehensive RBAC with roles (admin, script-manager, script-creator, script-executor)
- **Fine-grained Permissions**: Granular permissions for script operations (view, create, update, delete, execute)
- **Multi-tenant Isolation**: Client-based data separation and access control
- **Rate Limiting**: Per-user and per-client execution limits

### Script Security
- **AST-based Analysis**: Advanced Abstract Syntax Tree security analysis using Peast parser
- **Whitelist/Blacklist Patterns**: Configurable security patterns for allowed/forbidden operations
- **Input Validation**: All script inputs are sanitized and validated
- **Execution Sandbox**: Isolated Deno environment prevents system access
- **Resource Limits**: CPU, memory, and execution time constraints with cgroups enforcement
- **Watchdog Monitoring**: Real-time monitoring and automatic termination of runaway scripts
- **Kill-Switch Protection**: Emergency shutdown mechanism for security breaches

### Secret Management
- **Encrypted Storage**: AES-256 encrypted secret storage
- **Secret Rotation**: Automated and manual secret rotation capabilities
- **Expiration Management**: Configurable secret expiration and alerts
- **Usage Tracking**: Complete audit trail of secret access
- **Security Scoring**: Risk assessment based on usage patterns

### Monitoring & Auditing
- **Audit Trail**: Complete execution history and security events
- **Security Logging**: Comprehensive security event logging
- **Threat Detection**: Real-time security violation detection
- **Compliance Reporting**: Security compliance and audit reports
- **Prometheus Metrics**: Comprehensive metrics collection and alerting
- **Watchdog Alerts**: Real-time alerts for resource violations and security breaches

## Testing

```bash
# Run all tests
php artisan test

# Run security tests
php artisan test --group=security

# Run performance tests
php artisan test --group=performance

# Run tests in Docker
docker-compose exec app php artisan test
```

## CI/CD & Deployment

The project includes a comprehensive CI/CD pipeline:

### GitHub Actions Pipeline
- **Multi-PHP Testing**: Tests across PHP 8.1, 8.2, and 8.3
- **Security Scanning**: SAST analysis, AST security validation, and vulnerability scanning
- **Code Quality**: PHPStan, Psalm, PHPUnit, and Laravel Pint
- **Docker Build**: Multi-stage container builds with security scanning
- **Dependency Auditing**: Automated security vulnerability checks with composer audit
- **Deno Testing**: Deno sidecar testing and security validation

### Container Orchestration
- **Docker Compose**: Complete stack deployment
- **Resource Limits**: CPU and memory constraints
- **Health Checks**: Application and service health monitoring
- **Service Discovery**: Nginx reverse proxy and load balancing
- **Monitoring**: Application performance monitoring

### Infrastructure as Code
- **Dockerfiles**: Multi-stage builds for production and development
- **Configuration Management**: Environment-specific configurations
- **Secret Management**: Secure credential handling
- **Backup Strategies**: Database and application data backups

## Docker Production Deployment

### Build and Deploy
```bash
# Build production images
docker build --target production -t nice-scripting-solution:latest .
docker build -f docker/deno/Dockerfile -t deno-executor:latest docker/deno/

# Run with docker-compose (includes monitoring stack)
docker-compose up -d

# Check health
curl http://localhost/health
curl http://localhost:8080/health  # Deno executor
curl http://localhost:9090         # Prometheus
curl http://localhost:3000         # Grafana
```

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=db
DB_DATABASE=nice_scripting
DB_USERNAME=nice_scripting
DB_PASSWORD=secret
REDIS_HOST=redis
QUEUE_CONNECTION=redis
DENO_SERVICE_URL=http://deno-executor:8080
GRAFANA_PASSWORD=admin
```

### Monitoring Stack
- **Prometheus**: `http://localhost:9090`
- **Grafana**: `http://localhost:3000` (admin/admin)
- **AlertManager**: `http://localhost:9093`
- **Node Exporter**: `http://localhost:9100`

## Monitoring & Analytics

The solution includes comprehensive monitoring and analytics with Prometheus integration:

### Core Monitoring Features
- **Real-time Dashboard**: Live metrics and performance indicators with Chart.js visualization
- **Execution Metrics**: Performance and resource usage tracking via Prometheus
- **Error Tracking**: Failed executions and detailed exception analysis
- **Security Events**: Authentication, authorization, and security violation logs
- **Resource Usage**: CPU, memory, and database query monitoring
- **Health Checks**: System health endpoints for infrastructure monitoring
- **Alert System**: Configurable alerts for performance and security thresholds
- **WebSocket Support**: Real-time metrics updates for live dashboards
- **Metrics Export**: Prometheus-compatible metrics endpoint at `/metrics`

### Prometheus Stack Integration
- **Prometheus Server**: Metrics collection and storage
- **Grafana Dashboards**: Visual monitoring and alerting interface
- **AlertManager**: Centralized alert routing and notification
- **Node Exporter**: System-level metrics collection
- **Redis Exporter**: Redis performance monitoring
- **MySQL Exporter**: Database performance monitoring

### Kill-Switch Monitoring
- **Memory Threshold**: Automatic shutdown at >80% memory usage
- **CPU Threshold**: Protection against >85% CPU usage
- **Concurrent Executions**: Limits to prevent resource exhaustion
- **Failure Rate Monitoring**: Automatic response to high failure rates
- **Security Violations**: Real-time security threat detection

### Alerting Rules
- **Critical Alerts**: Memory/CPU thresholds, kill-switch triggers, service failures, runaway scripts
- **Warning Alerts**: Performance degradation, high failure rates, security violations, resource violations
- **Info Alerts**: Usage patterns and system status updates
- **Multi-channel Notifications**: Email, Slack, webhook integrations
- **Runaway Script Detection**: Automatic detection and termination of long-running or resource-intensive scripts

## API Documentation

Complete API documentation is available at `/api/documentation` when running in development mode.

## Contributing

Please review the contributing guidelines and ensure all tests pass before submitting pull requests.

## License

MIT License - see LICENSE file for details.