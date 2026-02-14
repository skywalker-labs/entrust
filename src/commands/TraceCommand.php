<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TraceCommand extends Command
{
    protected $signature = 'skywalker:trace {user_id} {permission} {--guard=}';
    protected $description = 'Trace how a user acquired a specific permission';

    public function handle(): void
    {
        $userId = $this->argument('user_id');
        $permissionName = $this->argument('permission');
        $guard = $this->option('guard') ?: Config::get('auth.defaults.guard');

        $userClass = Config::get('auth.providers.users.model');
        $user = $userClass::find($userId);

        if (!$user) {
            $this->error("User not found with ID: {$userId}");
            return;
        }

        $this->info("Tracing permission '{$permissionName}' for user: {$user->email} (ID: {$userId})");
        $this->newLine();

        $found = false;

        // 1. Check direct permissions
        foreach ($user->permissions as $perm) {
            if (\Illuminate\Support\Str::is($permissionName, $perm->name) && ($guard === null || $perm->guard_name === $guard)) {
                $this->line("<info>[Direct]</info> User has permission directly.");
                $this->renderDependencyPath($perm);
                $found = true;
            }
        }

        // 2. Check roles
        foreach ($user->roles as $role) {
            if ($role->hasPermission($permissionName, false, $guard)) {
                $this->line("<info>[Role: {$role->name}]</info> User has permission via this role.");
                $foundPerm = $this->findPermissionInRole($role, $permissionName, $guard);
                if ($foundPerm) {
                    $this->renderDependencyPath($foundPerm);
                }
                $found = true;
            }
        }

        if (!$found) {
            $this->warn("User does NOT have this permission.");
        }
    }

    protected function findPermissionInRole($role, $name, $guard): ?object
    {
        foreach ($role->perms as $perm) {
            if (\Illuminate\Support\Str::is($name, $perm->name) && ($guard === null || $perm->guard_name === $guard)) {
                return $perm;
            }
        }

        if ($role->parent) {
            return $this->findPermissionInRole($role->parent, $name, $guard);
        }

        return null;
    }

    protected function renderDependencyPath($permission): void
    {
        if (!empty($permission->depends_on)) {
            $deps = is_string($permission->depends_on) ? json_decode($permission->depends_on, true) : $permission->depends_on;
            if (is_array($deps)) {
                $this->line("  └─ Prerequisites: " . implode(', ', $deps));
            }
        }
    }
}
