# Deployment Guide

## NICE Scripting Solution - Production Deployment

This guide provides comprehensive instructions for deploying the NICE Scripting Solution to production environments.

## Prerequisites

### System Requirements
- **PHP**: 8.1 or higher
- **Node.js**: 16.x or higher
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Redis**: 6.0+ (for caching and queues)
- **Web Server**: Nginx or Apache
- **Docker**: 20.10+ (for containerized deployment)
- **Docker Compose**: 2.0+ (for orchestration)

### Additional Requirements for Enterprise Features
- **Prometheus**: For metrics collection
- **Grafana**: For metrics visualization (optional)
- **Git**: For version control and CI/CD
- **GitHub Actions**: For automated CI/CD pipeline
- **WebSocket Server**: For real-time metrics updates
- **Node.js**: For frontend build and Monaco editor

### Required PHP Extensions
```bash
# Install required extensions
sudo apt-get install php8.1-cli php8.1-fpm php8.1-mysql php8.1-redis php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd php8.1-bcmath
```

## Docker Deployment (Recommended)

### Quick Start with Docker Compose

```bash
# Clone repository
git clone https://github.com/your-org/nice-scripting-solution.git
cd nice-scripting-solution

# Configure environment
cp .env.example .env
# Edit .env file with your configuration

# Build and start services
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Build frontend assets
docker-compose exec app npm run build

# Run migrations
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan db:seed --class=RolePermissionSeeder

# Generate application key
docker-compose exec app php artisan key:generate
```

### Production Docker Deployment

```bash
# Build production image
docker build --target production -t nice-scripting-solution:latest .

# Run with production configuration
docker-compose -f docker-compose.prod.yml up -d

# Monitor services
docker-compose ps
docker-compose logs -f
```

### Docker Services Overview
- **app**: Main Laravel application (PHP-FPM)
- **deno-executor**: Secure Deno sidecar for script execution
- **nginx**: Web server and reverse proxy
- **mysql**: MySQL database
- **redis**: Cache and queue backend
- **worker**: Queue worker processes
- **scheduler**: Cron job scheduler
- **prometheus**: Metrics collection and monitoring
- **grafana**: Metrics visualization dashboard
- **alertmanager**: Alert routing and notification
- **node-exporter**: System metrics collection
- **redis-exporter**: Redis metrics collection
- **mysql-exporter**: MySQL metrics collection

### Container Resource Limits
```yaml
# Configured resource limits
app:
  limits:
    cpus: '1.0'
    memory: 1G
  reservations:
    cpus: '0.5'
    memory: 512M

deno-executor:
  limits:
    cpus: '0.5'
    memory: 256M
  reservations:
    cpus: '0.25'
    memory: 128M

worker:
  limits:
    cpus: '0.5'
    memory: 512M
  reservations:
    cpus: '0.25'
    memory: 256M

prometheus:
  limits:
    cpus: '0.5'
    memory: 512M
  reservations:
    cpus: '0.25'
    memory: 256M
```

## Manual Installation

### 1. Clone Repository
```bash
git clone https://github.com/your-org/nice-scripting-solution.git
cd nice-scripting-solution
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies and build frontend
npm install --production
npm run build
```

### 3. Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

#### Configure Environment Variables
```env
# Application
APP_NAME="NICE Scripting Solution"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nice_scripting
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis
QUEUE_PREFIX=nice_scripting

# Scripting Configuration
SCRIPTING_ENABLED=true
SCRIPT_TIMEOUT=30
SCRIPT_MEMORY_LIMIT=32
SCRIPT_MAX_CONCURRENT=10
SCRIPT_SECURITY_VALIDATION=true
SCRIPT_RESOURCE_MONITORING=true
SCRIPT_RATE_LIMITING=true
SCRIPT_AUDIT_LOGGING=true

# Deno Sidecar Configuration
DENO_SERVICE_URL=http://deno-executor:8080
DENO_EXECUTOR_TIMEOUT=30
DENO_EXECUTOR_MEMORY_LIMIT=256
DENO_EXECUTOR_CPU_LIMIT=0.5

# Security
SCRIPT_DATABASE_ACCESS=true
SCRIPT_ENABLE_WRITES=false
SCRIPT_HTTP_ACCESS=true
SCRIPT_EVENTS_ENABLED=true
SCRIPT_LOGGING_ENABLED=true

# Monitoring
SCRIPT_MONITORING_ENABLED=true
SCRIPT_ERROR_RATE_THRESHOLD=0.1
SCRIPT_AVG_TIME_THRESHOLD=5.0
SCRIPT_MEMORY_THRESHOLD=0.8
SCRIPT_CONCURRENT_THRESHOLD=8

# Kill-Switch Configuration
KILL_SWITCH_ENABLED=true
KILL_SWITCH_MEMORY_THRESHOLD=80
KILL_SWITCH_CPU_THRESHOLD=85
KILL_SWITCH_CONCURRENT_THRESHOLD=10
KILL_SWITCH_FAILURE_RATE_THRESHOLD=0.5

# Prometheus Metrics
PROMETHEUS_ENABLED=true
PROMETHEUS_NAMESPACE=nice_scripting
PROMETHEUS_METRICS_PATH=/metrics
PROMETHEUS_REDIS_KEY=prometheus_metrics

# Grafana Configuration
GRAFANA_ADMIN_PASSWORD=admin
GRAFANA_PLUGINS=grafana-clock-panel,grafana-simple-json-datasource

# AlertManager Configuration
ALERTMANAGER_WEBHOOK_URL=http://app:8000/api/alerts/webhook
ALERTMANAGER_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK

# WebSocket Server
WEBSOCKET_ENABLED=true
WEBSOCKET_PORT=8080
WEBSOCKET_HOST=0.0.0.0
```

### 4. Database Setup
```bash
# Run migrations
php artisan migrate --force

# Seed initial data
php artisan db:seed --class=ScriptingSeeder
```

### 5. Storage Setup
```bash
# Create storage link
php artisan storage:link

# Set proper permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## CI/CD Pipeline

### GitHub Actions Workflow
The project includes a comprehensive CI/CD pipeline with the following stages:

#### 1. Test Matrix
- **Multi-PHP Testing**: PHP 8.1, 8.2, 8.3
- **Database Testing**: MySQL 8.0
- **Redis Testing**: Redis 7.0
- **AST Security Analysis**: Automated security scanning

#### 2. Security Scanning
- **Static Analysis**: PHPStan level 8 analysis
- **Security Audit**: Composer audit for vulnerabilities
- **Code Quality**: Laravel Pint formatting checks
- **Container Scanning**: Trivy vulnerability scanning

#### 3. Docker Build
- **Multi-stage Build**: Development and production targets
- **Security Scanning**: Container vulnerability scanning
- **Health Checks**: Automated health verification

#### 4. Deployment Stages
- **Test**: Automated testing across environments
- **Code Quality**: Quality assurance checks
- **Security**: Security vulnerability scanning
- **Docker**: Container build and test

## Web Server Configuration

### Nginx Configuration (Docker)
The included Docker configuration provides:
- SSL/TLS termination
- Rate limiting
- Security headers
- Static asset optimization
- Health check endpoints

### SSL/TLS Configuration
```nginx
# SSL configuration
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /etc/ssl/certs/your-domain.crt;
    ssl_certificate_key /etc/ssl/private/your-domain.key;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

## Process Management

### Supervisor Configuration (Docker)
The Docker setup includes Supervisor for process management:
- **nginx**: Web server
- **php-fpm**: PHP FastCGI Process Manager
- **laravel-worker**: Queue worker processes
- **laravel-schedule**: Cron job scheduler

### Manual Supervisor Configuration
```ini
# /etc/supervisor/conf.d/nice-scripting-queue.conf
[program:nice-scripting-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nice-scripting-solution/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/nice-scripting-solution
autostart=true
autorestart=true
user=www-data
numprocs=4
```

## Security Configuration

### Role-Based Access Control Setup
```bash
# Seed roles and permissions
php artisan db:seed --class=RolePermissionSeeder

# Create admin user
php artisan tinker
>>> $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
>>> $user->assignRole('super-admin');
```

### Secret Management Setup
```bash
# Generate encryption key for secrets
php artisan key:generate

# Configure secret storage
php artisan config:cache

# Test secret management
php artisan tinker
>>> $client = Client::first();
>>> $secretManager = app(App\Services\Security\SecretManager::class);
>>> $secretManager->storeSecret($client, 'api.key', 'secret_value', ['type' => 'api_key']);
```

### Security Features Configuration
```env
# Enable security features
SCRIPT_SECURITY_VALIDATION=true
SCRIPT_AST_ANALYSIS=true
SCRIPT_RATE_LIMITING=true
SCRIPT_AUDIT_LOGGING=true

# Configure security limits
SCRIPT_MAX_EXECUTION_TIME=30
SCRIPT_MAX_MEMORY_MB=128
SCRIPT_MAX_CONCURRENT_EXECUTIONS=5
SCRIPT_RATE_LIMIT_PER_MINUTE=100

# Secret management
SECRET_ENCRYPTION_ENABLED=true
SECRET_ROTATION_ENABLED=true
SECRET_EXPIRATION_DAYS=90
```

## Database Optimization

### MySQL Configuration
```sql
-- Create database and user
CREATE DATABASE nice_scripting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nice_scripting'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON nice_scripting.* TO 'nice_scripting'@'localhost';
FLUSH PRIVILEGES;
```

### Database Backup
```bash
# Database backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/nice-scripting"
mkdir -p $BACKUP_DIR

# Database backup
docker-compose exec mysql mysqldump -u nice_scripting -p nice_scripting > $BACKUP_DIR/database_$DATE.sql

# Storage backup
docker-compose exec app tar -czf - /var/www/storage > $BACKUP_DIR/storage_$DATE.tar.gz

# Cleanup old backups (keep 7 days)
find $BACKUP_DIR -type f -mtime +7 -delete
```

## Performance Optimization

### PHP Configuration (Docker)
The Docker setup includes optimized PHP configuration:
- **OPcache**: Enabled with production settings
- **Memory limits**: Configured for production workloads
- **Security**: Disabled dangerous functions
- **Performance**: Optimized for production

### Redis Configuration
```conf
# Redis configuration
maxmemory 1gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

## Monitoring and Logging

### Health Checks
```bash
# Health check endpoint
curl http://localhost/health

# Expected response:
{
    "status": "healthy",
    "timestamp": "2024-01-01T00:00:00Z",
    "services": {
        "database": "healthy",
        "redis": "healthy",
        "queue": "healthy",
        "websocket": "healthy",
        "deno_executor": "healthy"
    }
}

# Deno executor health check
curl http://localhost:8080/health

# Expected response:
{
    "status": "healthy",
    "timestamp": "2024-01-01T00:00:00Z",
    "memory_usage": 45.2,
    "uptime": 3600
}
```

### Monitoring Stack Configuration

#### Prometheus Configuration
```yaml
# Access Prometheus at http://localhost:9090
# Key metrics endpoints:
# - http://localhost:8000/metrics (Laravel app)
# - http://localhost:8080/metrics (Deno executor)
# - http://localhost:9100/metrics (Node exporter)
# - http://localhost:9121/metrics (Redis exporter)
# - http://localhost:9104/metrics (MySQL exporter)
```

#### Grafana Dashboards
```bash
# Access Grafana at http://localhost:3000
# Default credentials: admin/admin
# Pre-configured dashboards:
# - Application Performance
# - Script Execution Metrics
# - Infrastructure Monitoring
# - Security Events
```

#### AlertManager Configuration
```yaml
# Access AlertManager at http://localhost:9093
# Configured alert routes:
# - Critical alerts: Email + Slack
# - Warning alerts: Slack only
# - Info alerts: Webhook only
```

#### Kill-Switch Monitoring
```bash
# Kill-switch status endpoint
curl http://localhost/api/kill-switch/status

# Expected response:
{
    "active": false,
    "thresholds": {
        "memory": 80,
        "cpu": 85,
        "concurrent_executions": 10,
        "failure_rate": 0.5
    },
    "current_values": {
        "memory": 65.4,
        "cpu": 42.1,
        "concurrent_executions": 3,
        "failure_rate": 0.02
    }
}
```

### Prometheus Metrics
```bash
# Prometheus metrics endpoint
curl http://localhost/metrics

# Key metrics available:
# - script_executions_total
# - script_execution_duration_seconds
# - script_memory_usage_bytes
# - script_errors_total
# - script_security_violations_total
# - active_script_executions
# - script_queue_size
```

### WebSocket Metrics Stream
```javascript
// Connect to real-time metrics
const ws = new WebSocket('ws://localhost:8080/metrics');
ws.onmessage = (event) => {
    const metrics = JSON.parse(event.data);
    console.log('Real-time metrics:', metrics);
};
```

### Log Management
```bash
# Docker logs
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f mysql

# Application logs
docker-compose exec app tail -f storage/logs/laravel.log
```

## Maintenance

### Regular Tasks
```bash
# Docker maintenance
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan queue:restart
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Container maintenance
docker-compose exec app composer install --no-dev --optimize-autoloader
docker system prune -f
```

### Updates
```bash
# Update application
git pull origin main
docker-compose build --no-cache
docker-compose up -d
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
docker-compose exec app php artisan queue:restart
```

## Troubleshooting

### Common Issues

#### Permission Issues
```bash
# Fix Docker permissions
docker-compose exec app chown -R www-data:www-data /var/www
docker-compose exec app chmod -R 755 /var/www
```

#### Database Connection Issues
```bash
# Test database connection
docker-compose exec app php artisan tinker
>>> DB::connection()->getPdo();
```

#### Container Issues
```bash
# Check container status
docker-compose ps

# View container logs
docker-compose logs app
docker-compose logs nginx
docker-compose logs mysql

# Restart services
docker-compose restart app
```

### Log Locations
- **Application logs**: `docker-compose logs app`
- **Nginx logs**: `docker-compose logs nginx`
- **MySQL logs**: `docker-compose logs mysql`
- **Redis logs**: `docker-compose logs redis`

## High Availability Setup

### Load Balancer Configuration
```nginx
upstream nice_scripting_backend {
    server app1.example.com:80;
    server app2.example.com:80;
    server app3.example.com:80;
}

server {
    listen 80;
    server_name your-domain.com;
    
    location / {
        proxy_pass http://nice_scripting_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Docker Swarm Deployment
```yaml
# docker-compose.swarm.yml
version: '3.8'

services:
  app:
    image: nice-scripting-solution:latest
    deploy:
      replicas: 3
      resources:
        limits:
          cpus: '1.0'
          memory: 1G
        reservations:
          cpus: '0.5'
          memory: 512M
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
```

## Disaster Recovery

### Backup Strategy
1. **Database**: Daily automated backups
2. **Storage**: Daily backup of uploaded files
3. **Code**: Git repository with tagged releases
4. **Configuration**: Backup of environment files

### Recovery Procedures
1. **Database Recovery**: Restore from latest backup
2. **File Recovery**: Restore storage from backup
3. **Service Recovery**: Restart Docker services
4. **Verification**: Run health checks

## Support

### Health Check Endpoints
- **GET /health**: Application health status
- **GET /health/database**: Database connectivity
- **GET /health/redis**: Redis connectivity
- **GET /health/queue**: Queue worker status
- **GET /health/websocket**: WebSocket server status

### API Endpoints
- **GET /metrics**: Prometheus metrics endpoint
- **GET /api/metrics/dashboard**: Dashboard metrics data
- **WS /ws/metrics**: Real-time metrics WebSocket stream
- **GET /api/scripts/validate**: Script validation endpoint
- **POST /api/scripts/test**: Script testing endpoint

For additional support, please refer to the main README.md file or contact the development team.