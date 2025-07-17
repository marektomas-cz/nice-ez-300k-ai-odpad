<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Script;
use App\Models\ScriptExecutionLog;

class ScriptingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo clients
        $clients = [
            [
                'name' => 'Demo Corporation',
                'code' => 'DEMO_CORP',
                'api_quota' => 10000,
                'rate_limit' => 100,
                'is_active' => true,
                'settings' => json_encode([
                    'enable_webhook_triggers' => true,
                    'enable_scheduled_scripts' => true,
                    'max_script_size' => 65535,
                    'allowed_domains' => ['api.example.com', 'webhook.example.com'],
                ]),
            ],
            [
                'name' => 'Tech Solutions Ltd',
                'code' => 'TECH_SOL',
                'api_quota' => 5000,
                'rate_limit' => 50,
                'is_active' => true,
                'settings' => json_encode([
                    'enable_webhook_triggers' => true,
                    'enable_scheduled_scripts' => false,
                    'max_script_size' => 32768,
                    'allowed_domains' => ['api.techsol.com'],
                ]),
            ],
            [
                'name' => 'Innovation Hub',
                'code' => 'INNOV_HUB',
                'api_quota' => 15000,
                'rate_limit' => 150,
                'is_active' => true,
                'settings' => json_encode([
                    'enable_webhook_triggers' => true,
                    'enable_scheduled_scripts' => true,
                    'max_script_size' => 131072,
                    'allowed_domains' => ['*'],
                ]),
            ],
        ];

        foreach ($clients as $clientData) {
            $client = Client::create($clientData);

            // Create sample scripts for each client
            $this->createSampleScripts($client);
        }
    }

    /**
     * Create sample scripts for a client
     */
    private function createSampleScripts(Client $client): void
    {
        $scripts = [
            [
                'name' => 'User Activity Monitor',
                'description' => 'Monitors user activity and sends notifications for inactive users',
                'code' => $this->getUserActivityScript(),
                'language' => 'javascript',
                'is_active' => true,
                'tags' => ['monitoring', 'users', 'notifications'],
                'configuration' => json_encode([
                    'memory_limit' => 16,
                    'time_limit' => 10,
                    'schedule' => [
                        'enabled' => true,
                        'frequency' => 'hourly',
                    ],
                    'triggers' => [
                        'manual' => true,
                        'webhook' => true,
                        'event' => ['user.login', 'user.logout'],
                    ],
                ]),
            ],
            [
                'name' => 'Data Backup Script',
                'description' => 'Automated backup of critical data with compression',
                'code' => $this->getDataBackupScript(),
                'language' => 'javascript',
                'is_active' => true,
                'tags' => ['backup', 'data', 'automation'],
                'configuration' => json_encode([
                    'memory_limit' => 64,
                    'time_limit' => 60,
                    'schedule' => [
                        'enabled' => true,
                        'frequency' => 'daily',
                        'time' => '02:00',
                    ],
                    'triggers' => [
                        'manual' => true,
                        'webhook' => false,
                        'event' => ['backup.requested'],
                    ],
                ]),
            ],
            [
                'name' => 'API Health Check',
                'description' => 'Monitors external API health and performance',
                'code' => $this->getApiHealthCheckScript(),
                'language' => 'javascript',
                'is_active' => true,
                'tags' => ['health-check', 'api', 'monitoring'],
                'configuration' => json_encode([
                    'memory_limit' => 8,
                    'time_limit' => 5,
                    'schedule' => [
                        'enabled' => true,
                        'frequency' => 'minutely',
                    ],
                    'triggers' => [
                        'manual' => true,
                        'webhook' => true,
                        'event' => ['api.check.requested'],
                    ],
                ]),
            ],
            [
                'name' => 'Order Processing Automation',
                'description' => 'Automates order processing workflow',
                'code' => $this->getOrderProcessingScript(),
                'language' => 'javascript',
                'is_active' => false, // Disabled for demo
                'tags' => ['orders', 'automation', 'workflow'],
                'configuration' => json_encode([
                    'memory_limit' => 32,
                    'time_limit' => 30,
                    'schedule' => [
                        'enabled' => false,
                    ],
                    'triggers' => [
                        'manual' => true,
                        'webhook' => true,
                        'event' => ['order.created', 'order.updated'],
                    ],
                ]),
            ],
            [
                'name' => 'Email Campaign Trigger',
                'description' => 'Triggers email campaigns based on user behavior',
                'code' => $this->getEmailCampaignScript(),
                'language' => 'javascript',
                'is_active' => true,
                'tags' => ['email', 'marketing', 'campaign'],
                'configuration' => json_encode([
                    'memory_limit' => 24,
                    'time_limit' => 20,
                    'schedule' => [
                        'enabled' => true,
                        'frequency' => 'weekly',
                        'day' => 'monday',
                        'time' => '10:00',
                    ],
                    'triggers' => [
                        'manual' => true,
                        'webhook' => true,
                        'event' => ['user.milestone.reached'],
                    ],
                ]),
            ],
        ];

        foreach ($scripts as $scriptData) {
            $script = $client->scripts()->create($scriptData);

            // Create some execution logs for demonstration
            $this->createSampleExecutionLogs($script);
        }
    }

    /**
     * Create sample execution logs for a script
     */
    private function createSampleExecutionLogs(Script $script): void
    {
        $executionTypes = ['manual', 'scheduled', 'webhook', 'event'];
        $statuses = ['success', 'success', 'success', 'failed']; // 75% success rate

        // Create logs for the past 30 days
        for ($i = 0; $i < 30; $i++) {
            $executionsPerDay = rand(1, 5);
            
            for ($j = 0; $j < $executionsPerDay; $j++) {
                $createdAt = now()->subDays($i)->addHours(rand(0, 23))->addMinutes(rand(0, 59));
                $status = $statuses[array_rand($statuses)];
                $executionTime = $status === 'success' ? rand(100, 5000) / 1000 : null;
                
                $executionLog = ScriptExecutionLog::create([
                    'script_id' => $script->id,
                    'client_id' => $script->client_id,
                    'trigger_type' => $executionTypes[array_rand($executionTypes)],
                    'status' => $status,
                    'execution_time' => $executionTime,
                    'memory_used' => $status === 'success' ? rand(1024, 8192) : null,
                    'cpu_usage' => $status === 'success' ? rand(10, 80) / 100 : null,
                    'output' => $status === 'success' ? $this->getSuccessOutput() : null,
                    'error_message' => $status === 'failed' ? $this->getErrorMessage() : null,
                    'execution_context' => json_encode([
                        'user_id' => rand(1, 100),
                        'session_id' => 'sess_' . uniqid(),
                        'ip_address' => $this->getRandomIp(),
                    ]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Add some security flags for failed executions
                if ($status === 'failed' && rand(1, 3) === 1) {
                    $executionLog->update([
                        'security_flags' => json_encode([
                            [
                                'type' => 'rate_limit',
                                'message' => 'Rate limit exceeded',
                                'severity' => 'medium',
                                'timestamp' => $createdAt->toISOString(),
                            ],
                        ]),
                    ]);
                }
            }
        }
    }

    /**
     * Sample script codes
     */
    private function getUserActivityScript(): string
    {
        return <<<'JS'
// User Activity Monitor Script
const inactiveThreshold = 24 * 60 * 60 * 1000; // 24 hours in milliseconds
const currentTime = api.utils.now();

// Query for users who haven't logged in recently
const inactiveUsers = api.database.select('users', 
    ['id', 'name', 'email', 'last_login_at'], 
    {
        active: true,
        notifications_enabled: true
    }
);

const users = JSON.parse(inactiveUsers);
let notificationCount = 0;

users.forEach(user => {
    const lastLogin = new Date(user.last_login_at).getTime();
    const timeSinceLogin = currentTime - lastLogin;
    
    if (timeSinceLogin > inactiveThreshold) {
        // Send notification event
        api.events.dispatch('user.inactive.notification', {
            user_id: user.id,
            name: user.name,
            email: user.email,
            days_inactive: Math.floor(timeSinceLogin / (24 * 60 * 60 * 1000)),
            notification_type: 'inactive_user_alert'
        });
        
        notificationCount++;
        api.log.info(`Sent inactive notification to user: ${user.name}`);
    }
});

api.log.info(`Processed ${users.length} users, sent ${notificationCount} notifications`);

return {
    success: true,
    processed_users: users.length,
    notifications_sent: notificationCount,
    inactive_threshold_hours: inactiveThreshold / (60 * 60 * 1000)
};
JS;
    }

    private function getDataBackupScript(): string
    {
        return <<<'JS'
// Data Backup Script
const backupTables = ['users', 'orders', 'products', 'audit_logs'];
const backupTimestamp = api.utils.now();
const backupId = api.utils.uuid();

api.log.info(`Starting backup process: ${backupId}`);

let backupResults = [];

backupTables.forEach(table => {
    try {
        // Get table data
        const data = api.database.select(table, ['*']);
        const records = JSON.parse(data);
        
        // Create backup entry
        const backupEntry = {
            backup_id: backupId,
            table_name: table,
            record_count: records.length,
            created_at: backupTimestamp,
            status: 'completed'
        };
        
        // Log backup info
        api.database.insert('backups', backupEntry);
        
        backupResults.push({
            table: table,
            records: records.length,
            status: 'success'
        });
        
        api.log.info(`Backed up ${records.length} records from ${table}`);
        
    } catch (error) {
        api.log.error(`Backup failed for table ${table}: ${error.message}`);
        backupResults.push({
            table: table,
            records: 0,
            status: 'failed',
            error: error.message
        });
    }
});

// Send backup completion event
api.events.dispatch('backup.completed', {
    backup_id: backupId,
    timestamp: backupTimestamp,
    results: backupResults
});

const totalRecords = backupResults.reduce((sum, result) => sum + result.records, 0);
api.log.info(`Backup completed: ${totalRecords} total records`);

return {
    success: true,
    backup_id: backupId,
    total_records: totalRecords,
    tables_backed_up: backupResults.length,
    results: backupResults
};
JS;
    }

    private function getApiHealthCheckScript(): string
    {
        return <<<'JS'
// API Health Check Script
const endpoints = [
    'https://api.example.com/health',
    'https://api.example.com/status',
    'https://webhook.example.com/ping'
];

let healthResults = [];

endpoints.forEach(endpoint => {
    try {
        const startTime = api.utils.now();
        const response = api.http.get(endpoint);
        const endTime = api.utils.now();
        const responseTime = endTime - startTime;
        
        const healthCheck = {
            endpoint: endpoint,
            status: 'healthy',
            response_time: responseTime,
            timestamp: endTime
        };
        
        healthResults.push(healthCheck);
        api.log.info(`Health check OK: ${endpoint} (${responseTime}ms)`);
        
    } catch (error) {
        const healthCheck = {
            endpoint: endpoint,
            status: 'unhealthy',
            error: error.message,
            timestamp: api.utils.now()
        };
        
        healthResults.push(healthCheck);
        api.log.error(`Health check failed: ${endpoint} - ${error.message}`);
        
        // Trigger alert for unhealthy endpoints
        api.events.dispatch('api.health.alert', {
            endpoint: endpoint,
            error: error.message,
            severity: 'high'
        });
    }
});

const healthyCount = healthResults.filter(result => result.status === 'healthy').length;
const overallHealth = healthyCount === endpoints.length ? 'healthy' : 'degraded';

return {
    success: true,
    overall_health: overallHealth,
    healthy_endpoints: healthyCount,
    total_endpoints: endpoints.length,
    results: healthResults
};
JS;
    }

    private function getOrderProcessingScript(): string
    {
        return <<<'JS'
// Order Processing Automation Script
const pendingOrders = api.database.select('orders', 
    ['id', 'user_id', 'total_amount', 'status', 'created_at'], 
    { status: 'pending' }
);

const orders = JSON.parse(pendingOrders);
let processedCount = 0;

orders.forEach(order => {
    try {
        // Validate order
        if (order.total_amount <= 0) {
            throw new Error('Invalid order amount');
        }
        
        // Process payment
        const paymentResult = api.http.post('https://payment.example.com/process', {
            order_id: order.id,
            amount: order.total_amount,
            currency: 'USD'
        });
        
        const payment = JSON.parse(paymentResult);
        
        if (payment.status === 'success') {
            // Update order status
            api.database.update('orders', { id: order.id }, {
                status: 'processing',
                payment_id: payment.id,
                processed_at: api.utils.now()
            });
            
            // Send confirmation
            api.events.dispatch('order.confirmed', {
                order_id: order.id,
                user_id: order.user_id,
                amount: order.total_amount
            });
            
            processedCount++;
            api.log.info(`Order ${order.id} processed successfully`);
        }
        
    } catch (error) {
        api.log.error(`Failed to process order ${order.id}: ${error.message}`);
        
        // Update order with error
        api.database.update('orders', { id: order.id }, {
            status: 'failed',
            error_message: error.message,
            failed_at: api.utils.now()
        });
    }
});

return {
    success: true,
    total_orders: orders.length,
    processed_orders: processedCount,
    failed_orders: orders.length - processedCount
};
JS;
    }

    private function getEmailCampaignScript(): string
    {
        return <<<'JS'
// Email Campaign Trigger Script
const campaignType = 'weekly_digest';
const segmentFilters = {
    active: true,
    email_verified: true,
    marketing_opt_in: true
};

// Get eligible users
const eligibleUsers = api.database.select('users', 
    ['id', 'email', 'name', 'preferences'], 
    segmentFilters
);

const users = JSON.parse(eligibleUsers);
let campaignsSent = 0;

users.forEach(user => {
    try {
        const preferences = JSON.parse(user.preferences || '{}');
        
        // Check if user wants weekly emails
        if (preferences.weekly_digest !== false) {
            // Create personalized content
            const campaignData = {
                user_id: user.id,
                email: user.email,
                name: user.name,
                campaign_type: campaignType,
                template: 'weekly_digest',
                personalization: {
                    name: user.name,
                    week: new Date().toISOString().slice(0, 10)
                }
            };
            
            // Trigger email campaign
            api.events.dispatch('email.campaign.send', campaignData);
            
            campaignsSent++;
            api.log.info(`Queued campaign for user: ${user.email}`);
        }
        
    } catch (error) {
        api.log.error(`Failed to process campaign for user ${user.id}: ${error.message}`);
    }
});

// Record campaign metrics
const campaignMetrics = {
    campaign_id: api.utils.uuid(),
    campaign_type: campaignType,
    total_eligible: users.length,
    campaigns_sent: campaignsSent,
    sent_at: api.utils.now()
};

api.database.insert('campaign_metrics', campaignMetrics);

return {
    success: true,
    campaign_type: campaignType,
    eligible_users: users.length,
    campaigns_sent: campaignsSent,
    metrics: campaignMetrics
};
JS;
    }

    /**
     * Helper methods for generating sample data
     */
    private function getSuccessOutput(): string
    {
        $outputs = [
            '{"success": true, "processed": 15, "status": "completed"}',
            '{"result": "operation_successful", "items": 23, "duration": 1.5}',
            '{"success": true, "message": "Script executed successfully", "data": {"count": 42}}',
            '{"status": "ok", "records_processed": 8, "warnings": 0}',
            '{"success": true, "response_time": 850, "cache_hit": true}',
        ];
        
        return $outputs[array_rand($outputs)];
    }

    private function getErrorMessage(): string
    {
        $errors = [
            'Rate limit exceeded. Please try again later.',
            'Database connection timeout',
            'Invalid JSON in API response',
            'Insufficient permissions to access resource',
            'Script execution timeout after 30 seconds',
            'Memory limit exceeded (32MB)',
            'External API returned HTTP 500 error',
            'Validation failed: required field missing',
        ];
        
        return $errors[array_rand($errors)];
    }

    private function getRandomIp(): string
    {
        return rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
    }
}