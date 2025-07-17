<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Script permissions
            'view scripts',
            'create scripts',
            'update scripts',
            'delete scripts',
            'execute scripts',
            'update own scripts',
            'delete own scripts',
            'export scripts',
            'import scripts',
            'restore scripts',
            'manage script versions',
            
            // Execution permissions
            'view execution logs',
            'execute scripts manually',
            'schedule scripts',
            'cancel executions',
            
            // Monitoring permissions
            'view monitoring',
            'view security reports',
            'view system metrics',
            'configure alerts',
            
            // Client management
            'manage client',
            'view client settings',
            'update client settings',
            'manage client users',
            
            // System administration
            'manage users',
            'manage roles',
            'manage permissions',
            'system administration',
            'view audit logs',
            
            // Advanced features
            'manage webhooks',
            'manage api keys',
            'manage integrations',
            'configure security',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $this->createRoles();
    }

    /**
     * Create roles and assign permissions
     */
    private function createRoles(): void
    {
        // Super Admin - All permissions
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - Most permissions except super admin functions
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo([
            'view scripts',
            'create scripts',
            'update scripts',
            'delete scripts',
            'execute scripts',
            'update own scripts',
            'delete own scripts',
            'export scripts',
            'import scripts',
            'restore scripts',
            'manage script versions',
            'view execution logs',
            'execute scripts manually',
            'schedule scripts',
            'cancel executions',
            'view monitoring',
            'view security reports',
            'view system metrics',
            'configure alerts',
            'manage client',
            'view client settings',
            'update client settings',
            'manage client users',
            'view audit logs',
            'manage webhooks',
            'manage api keys',
            'manage integrations',
            'configure security',
        ]);

        // Script Manager - Full script management
        $scriptManager = Role::create(['name' => 'script-manager']);
        $scriptManager->givePermissionTo([
            'view scripts',
            'create scripts',
            'update scripts',
            'delete scripts',
            'execute scripts',
            'update own scripts',
            'delete own scripts',
            'export scripts',
            'import scripts',
            'manage script versions',
            'view execution logs',
            'execute scripts manually',
            'schedule scripts',
            'cancel executions',
            'view monitoring',
            'view security reports',
            'manage webhooks',
        ]);

        // Script Creator - Can create and manage own scripts
        $scriptCreator = Role::create(['name' => 'script-creator']);
        $scriptCreator->givePermissionTo([
            'view scripts',
            'create scripts',
            'update own scripts',
            'delete own scripts',
            'execute scripts',
            'view execution logs',
            'execute scripts manually',
            'export scripts',
        ]);

        // Script Executor - Can execute scripts
        $scriptExecutor = Role::create(['name' => 'script-executor']);
        $scriptExecutor->givePermissionTo([
            'view scripts',
            'execute scripts',
            'execute scripts manually',
            'view execution logs',
        ]);

        // Script Viewer - Read-only access
        $scriptViewer = Role::create(['name' => 'script-viewer']);
        $scriptViewer->givePermissionTo([
            'view scripts',
            'view execution logs',
            'view monitoring',
        ]);

        // Monitoring Viewer - Can view monitoring and reports
        $monitoringViewer = Role::create(['name' => 'monitoring-viewer']);
        $monitoringViewer->givePermissionTo([
            'view scripts',
            'view monitoring',
            'view security reports',
            'view system metrics',
            'view execution logs',
        ]);

        // Security Auditor - Can view security reports and logs
        $securityAuditor = Role::create(['name' => 'security-auditor']);
        $securityAuditor->givePermissionTo([
            'view scripts',
            'view security reports',
            'view execution logs',
            'view monitoring',
            'view audit logs',
            'configure security',
        ]);

        // Client Manager - Can manage client settings and users
        $clientManager = Role::create(['name' => 'client-manager']);
        $clientManager->givePermissionTo([
            'view scripts',
            'create scripts',
            'update scripts',
            'delete scripts',
            'execute scripts',
            'view execution logs',
            'view monitoring',
            'manage client',
            'view client settings',
            'update client settings',
            'manage client users',
            'manage webhooks',
            'manage api keys',
        ]);

        // Developer - Full script development capabilities
        $developer = Role::create(['name' => 'developer']);
        $developer->givePermissionTo([
            'view scripts',
            'create scripts',
            'update scripts',
            'delete scripts',
            'execute scripts',
            'update own scripts',
            'delete own scripts',
            'export scripts',
            'import scripts',
            'manage script versions',
            'view execution logs',
            'execute scripts manually',
            'schedule scripts',
            'view monitoring',
            'view security reports',
            'manage webhooks',
            'manage integrations',
        ]);

        // API User - For programmatic access
        $apiUser = Role::create(['name' => 'api-user']);
        $apiUser->givePermissionTo([
            'view scripts',
            'execute scripts',
            'view execution logs',
        ]);
    }
}