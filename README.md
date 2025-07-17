# NICE Scripting Solution

## Enterprise-grade In-App Scripting Solution for Laravel

This project implements a secure, scalable in-app scripting solution for multi-tenant Laravel applications, designed to meet enterprise security and performance standards.

## Architecture Overview

The solution follows a layered architecture with strict security boundaries:

- **Client Interface Layer**: User-friendly script management with syntax highlighting
- **Security Layer**: Permission validation, rate limiting, and input sanitization  
- **Execution Engine**: AST-based secure JavaScript analysis
- **Data Layer**: Comprehensive logging and audit trails

## Key Features

### 🔒 Security-First Design
- **AST Security Analysis**: Advanced Abstract Syntax Tree based code analysis using Peast parser
- **Resource Limits**: CPU, memory, and execution time constraints
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
- Node.js 18+ (for frontend build)
- npm or yarn (package manager)

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

## Script Versioning

The solution includes comprehensive version control for scripts:

### Version Management
- **Semantic Versioning**: Automatic version numbering (major.minor.patch)
- **Change Tracking**: Complete diff calculation between versions
- **Version Metadata**: Creator, timestamps, and change notes
- **Rollback Capability**: Easy rollback to previous versions

### Usage Examples

```php
// Create a new version
$version = $script->createVersion('Fixed performance issue', auth()->user());

// Rollback to specific version
$script->rollbackToVersion($version->id);

// Compare versions
$diff = $version->getDiff($previousVersion);

// Get version history
$versions = $script->versions()->orderBy('created_at', 'desc')->get();
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
- **Execution Sandbox**: Isolated environment prevents system access
- **Resource Limits**: CPU, memory, and execution time constraints

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
- **Security Scanning**: SAST analysis and vulnerability scanning
- **Code Quality**: PHPStan, PHPUnit, and Laravel Pint
- **Docker Build**: Multi-stage container builds
- **Dependency Auditing**: Automated security vulnerability checks

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
# Build production image
docker build --target production -t nice-scripting-solution:latest .

# Run with docker-compose
docker-compose -f docker-compose.prod.yml up -d

# Check health
curl http://localhost/health
```

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=scripting_solution
REDIS_HOST=redis
QUEUE_CONNECTION=redis
```

## Monitoring & Analytics

The solution includes comprehensive monitoring and analytics:

- **Real-time Dashboard**: Live metrics and performance indicators with Chart.js visualization
- **Execution Metrics**: Performance and resource usage tracking via Prometheus
- **Error Tracking**: Failed executions and detailed exception analysis
- **Security Events**: Authentication, authorization, and security violation logs
- **Resource Usage**: CPU, memory, and database query monitoring
- **Health Checks**: System health endpoints for infrastructure monitoring
- **Alert System**: Configurable alerts for performance and security thresholds
- **WebSocket Support**: Real-time metrics updates for live dashboards
- **Metrics Export**: Prometheus-compatible metrics endpoint at `/metrics`

## API Documentation

Complete API documentation is available at `/api/documentation` when running in development mode.

## Contributing

Please review the contributing guidelines and ensure all tests pass before submitting pull requests.

## License

MIT License - see LICENSE file for details.