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
- **V8Js Extension**: Required for JavaScript execution

### Required PHP Extensions
```bash
# Install required extensions
sudo apt-get install php8.1-cli php8.1-fpm php8.1-mysql php8.1-redis php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd

# Install V8Js extension
sudo apt-get install php8.1-v8js
```

## Installation

### 1. Clone Repository
```bash
git clone https://github.com/marektomas-cz/nice-ez-300k-ai-odpad.git nice-scripting-solution
cd nice-scripting-solution
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
npm install --production

# Build frontend assets
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

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
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

## Web Server Configuration

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/nice-scripting-solution/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### SSL/TLS Configuration
```nginx
# SSL configuration
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /etc/ssl/certs/your-domain.crt;
    ssl_certificate_key /etc/ssl/private/your-domain.key;
    
    # ... rest of configuration
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

## Process Management

### Supervisor Configuration
```ini
# /etc/supervisor/conf.d/nice-scripting-queue.conf
[program:nice-scripting-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nice-scripting-solution/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/nice-scripting-solution
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/nice-scripting-queue.log
stopwaitsecs=3600
```

### Scheduled Tasks
```bash
# Add to crontab
* * * * * cd /var/www/nice-scripting-solution && php artisan schedule:run >> /dev/null 2>&1
```

## Security Configuration

### File Permissions
```bash
# Set proper ownership
chown -R www-data:www-data /var/www/nice-scripting-solution

# Set directory permissions
find /var/www/nice-scripting-solution -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/nice-scripting-solution -type f -exec chmod 644 {} \;

# Set executable permissions
chmod +x /var/www/nice-scripting-solution/artisan
```

### Firewall Configuration
```bash
# UFW rules
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw deny 3306/tcp
ufw deny 6379/tcp
ufw --force enable
```

### Application Security
```bash
# Clear and cache configuration
php artisan config:clear
php artisan config:cache

# Clear and cache routes
php artisan route:clear
php artisan route:cache

# Clear and cache views
php artisan view:clear
php artisan view:cache
```

## Monitoring and Logging

### Log Configuration
```bash
# Create log directories
mkdir -p /var/log/nice-scripting
chown -R www-data:www-data /var/log/nice-scripting

# Configure log rotation
cat > /etc/logrotate.d/nice-scripting << EOF
/var/log/nice-scripting/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
EOF
```

### Health Checks
```bash
# Create health check script
cat > /usr/local/bin/nice-scripting-health << 'EOF'
#!/bin/bash
response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/health)
if [ $response -eq 200 ]; then
    echo "OK"
    exit 0
else
    echo "FAIL"
    exit 1
fi
EOF

chmod +x /usr/local/bin/nice-scripting-health
```

## Database Optimization

### MySQL Configuration
```sql
-- Create database and user
CREATE DATABASE nice_scripting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'nice_scripting'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON nice_scripting.* TO 'nice_scripting'@'localhost';
FLUSH PRIVILEGES;

-- Optimize settings
SET GLOBAL innodb_buffer_pool_size = 2G;
SET GLOBAL innodb_log_file_size = 256M;
SET GLOBAL max_connections = 200;
```

### Database Backup
```bash
# Create backup script
cat > /usr/local/bin/nice-scripting-backup << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/nice-scripting"
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u nice_scripting -p nice_scripting > $BACKUP_DIR/database_$DATE.sql

# Storage backup
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz /var/www/nice-scripting-solution/storage

# Cleanup old backups (keep 7 days)
find $BACKUP_DIR -type f -mtime +7 -delete
EOF

chmod +x /usr/local/bin/nice-scripting-backup

# Add to crontab
0 2 * * * /usr/local/bin/nice-scripting-backup
```

## Performance Optimization

### PHP Configuration
```ini
# /etc/php/8.1/fpm/php.ini
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

# OPcache configuration
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### Redis Configuration
```conf
# /etc/redis/redis.conf
maxmemory 1gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

## Monitoring Integration

### Prometheus Metrics
```yaml
# prometheus.yml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'nice-scripting'
    static_configs:
      - targets: ['localhost:9090']
    metrics_path: '/metrics'
    scrape_interval: 30s
```

### Grafana Dashboard
```json
{
  "dashboard": {
    "title": "NICE Scripting Solution",
    "panels": [
      {
        "title": "Script Executions",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(script_executions_total[5m])",
            "legendFormat": "Executions/sec"
          }
        ]
      },
      {
        "title": "Error Rate",
        "type": "singlestat",
        "targets": [
          {
            "expr": "rate(script_errors_total[5m]) / rate(script_executions_total[5m])",
            "legendFormat": "Error Rate"
          }
        ]
      }
    ]
  }
}
```

## Troubleshooting

### Common Issues

#### V8Js Installation Issues
```bash
# Ubuntu/Debian
sudo apt-get install libv8-dev
sudo pecl install v8js

# Add to php.ini
extension=v8js.so
```

#### Permission Issues
```bash
# Fix ownership
chown -R www-data:www-data /var/www/nice-scripting-solution

# Fix permissions
chmod -R 755 /var/www/nice-scripting-solution
chmod -R 775 /var/www/nice-scripting-solution/storage
chmod -R 775 /var/www/nice-scripting-solution/bootstrap/cache
```

#### Database Connection Issues
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### Log Locations
- Application logs: `/var/www/nice-scripting-solution/storage/logs/`
- Nginx logs: `/var/log/nginx/`
- PHP-FPM logs: `/var/log/php8.1-fpm.log`
- Supervisor logs: `/var/log/supervisor/`

## Maintenance

### Regular Tasks
```bash
# Daily maintenance
php artisan cache:clear
php artisan queue:restart
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Weekly maintenance
php artisan scripts:cleanup-logs
php artisan optimize:clear
php artisan optimize

# Monthly maintenance
php artisan scripts:archive-old-executions
composer update --no-dev
npm update
```

### Updates
```bash
# Update application
git pull origin main
composer install --no-dev --optimize-autoloader
npm install --production
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

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

### Database Replication
```sql
-- Master configuration
SET GLOBAL binlog_format = 'ROW';
SET GLOBAL server_id = 1;

-- Slave configuration
SET GLOBAL server_id = 2;
CHANGE MASTER TO
    MASTER_HOST='master-ip',
    MASTER_USER='replication_user',
    MASTER_PASSWORD='replication_password',
    MASTER_LOG_FILE='mysql-bin.000001',
    MASTER_LOG_POS=0;
START SLAVE;
```

## Disaster Recovery

### Backup Strategy
1. **Database**: Daily full backup, hourly incremental
2. **Storage**: Daily backup of uploaded files
3. **Code**: Git repository with tags for releases
4. **Configuration**: Backup of environment files and server configs

### Recovery Procedures
1. **Database Recovery**: Restore from latest backup
2. **File Recovery**: Restore storage from backup
3. **Service Recovery**: Restart services in correct order
4. **Verification**: Run health checks and smoke tests

## Support

### Health Check Endpoint
```php
// GET /health
{
    "status": "healthy",
    "timestamp": "2024-01-01T00:00:00Z",
    "services": {
        "database": "healthy",
        "redis": "healthy",
        "queue": "healthy"
    }
}
```

### Metrics Endpoint
```php
// GET /metrics
script_executions_total{status="success"} 1234
script_executions_total{status="failed"} 56
script_execution_duration_seconds{quantile="0.5"} 0.123
script_execution_duration_seconds{quantile="0.95"} 0.456
```

For additional support, please refer to the main README.md file or contact the development team.