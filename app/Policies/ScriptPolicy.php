<?php

namespace App\Policies;

use App\Models\Script;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScriptPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any scripts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view scripts') || $user->hasRole(['admin', 'script-viewer']);
    }

    /**
     * Determine whether the user can view the script.
     */
    public function view(User $user, Script $script): bool
    {
        // Admin can view any script
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view scripts from their own client
        if ($user->isSameClient($script)) {
            return $user->hasPermissionTo('view scripts') || $user->hasRole('script-viewer');
        }

        return false;
    }

    /**
     * Determine whether the user can create scripts.
     */
    public function create(User $user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return $user->hasPermissionTo('create scripts') || $user->hasRole(['admin', 'script-creator']);
    }

    /**
     * Determine whether the user can update the script.
     */
    public function update(User $user, Script $script): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Admin can update any script
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can update scripts from their own client
        if ($user->isSameClient($script)) {
            // Check if user has general update permission
            if ($user->hasPermissionTo('update scripts') || $user->hasRole('script-editor')) {
                return true;
            }

            // Or if they created the script and have basic permissions
            if ($script->created_by === $user->id && $user->hasPermissionTo('update own scripts')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the script.
     */
    public function delete(User $user, Script $script): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Admin can delete any script
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can delete scripts from their own client
        if ($user->isSameClient($script)) {
            // Check if user has general delete permission
            if ($user->hasPermissionTo('delete scripts') || $user->hasRole('script-manager')) {
                return true;
            }

            // Or if they created the script and have basic permissions
            if ($script->created_by === $user->id && $user->hasPermissionTo('delete own scripts')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can execute the script.
     */
    public function execute(User $user, Script $script): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Script must be active
        if (!$script->is_active) {
            return false;
        }

        // Check rate limits
        if (!$user->isWithinRateLimit()) {
            return false;
        }

        // Check monthly quota
        if (!$user->isWithinMonthlyQuota()) {
            return false;
        }

        // Admin can execute any script
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can execute scripts from their own client
        if ($user->isSameClient($script)) {
            return $user->hasPermissionTo('execute scripts') || $user->hasRole('script-executor');
        }

        return false;
    }

    /**
     * Determine whether the user can restore the script.
     */
    public function restore(User $user, Script $script): bool
    {
        return $user->hasRole('admin') || 
               ($user->isSameClient($script) && $user->hasPermissionTo('restore scripts'));
    }

    /**
     * Determine whether the user can permanently delete the script.
     */
    public function forceDelete(User $user, Script $script): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can manage script versions.
     */
    public function manageVersions(User $user, Script $script): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Admin can manage versions of any script
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can manage versions of scripts from their own client
        if ($user->isSameClient($script)) {
            return $user->hasPermissionTo('manage script versions') || $user->hasRole('script-manager');
        }

        return false;
    }

    /**
     * Determine whether the user can view script execution logs.
     */
    public function viewExecutionLogs(User $user, Script $script): bool
    {
        // Admin can view any execution logs
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view execution logs from their own client
        if ($user->isSameClient($script)) {
            return $user->hasPermissionTo('view execution logs') || $user->hasRole(['script-viewer', 'monitoring-viewer']);
        }

        return false;
    }

    /**
     * Determine whether the user can export the script.
     */
    public function export(User $user, Script $script): bool
    {
        // Admin can export any script
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can export scripts from their own client
        if ($user->isSameClient($script)) {
            return $user->hasPermissionTo('export scripts') || $user->hasRole('script-manager');
        }

        return false;
    }

    /**
     * Determine whether the user can import scripts.
     */
    public function import(User $user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        return $user->hasPermissionTo('import scripts') || $user->hasRole(['admin', 'script-manager']);
    }

    /**
     * Determine whether the user can clone the script.
     */
    public function clone(User $user, Script $script): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Admin can clone any script
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can clone scripts from their own client if they can create scripts
        if ($user->isSameClient($script)) {
            return $this->create($user) && $this->view($user, $script);
        }

        return false;
    }

    /**
     * Determine whether the user can view script security report.
     */
    public function viewSecurityReport(User $user, Script $script): bool
    {
        // Admin can view any security report
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view security reports from their own client
        if ($user->isSameClient($script)) {
            return $user->hasPermissionTo('view security reports') || $user->hasRole(['script-manager', 'security-auditor']);
        }

        return false;
    }

    /**
     * Determine whether the user can toggle script status.
     */
    public function toggleStatus(User $user, Script $script): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Admin can toggle any script status
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can toggle scripts from their own client if they can update
        if ($user->isSameClient($script)) {
            return $this->update($user, $script);
        }

        return false;
    }
}