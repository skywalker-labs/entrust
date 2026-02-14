<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ScannerCommand extends Command
{
    protected $signature = 'skywalker:scan';
    protected $description = 'Scan the system for potential ACL security risks';

    public function handle(): void
    {
        $this->info('Starting Security Scan...');
        $this->newLine();

        $risks = 0;

        // 1. Check for Roles with no permissions
        $roleClass = Config::get('entrust.role');
        $emptyRoles = $roleClass::has('perms', '<', 1)->get();
        if ($emptyRoles->isNotEmpty()) {
            $this->warn("[Warning] Found " . $emptyRoles->count() . " roles with NO permissions.");
            foreach ($emptyRoles as $role) {
                $this->line(" - Role: {$role->name}");
            }
            $risks++;
        }

        // 2. Check for Over-Privileged Users (Users with many roles)
        $userClass = Config::get('auth.providers.users.model');
        $manyRolesUsers = $userClass::whereHas('roles', null, '>', 3)->get();
        if ($manyRolesUsers->isNotEmpty()) {
            $this->warn("[Insight] Found " . $manyRolesUsers->count() . " users with more than 3 roles. Consider merging roles.");
            $risks++;
        }

        // 3. Check for Permissions with no roles or users
        $permClass = Config::get('entrust.permission');
        $orphanPerms = $permClass::doesntHave('roles')->get();
        if ($orphanPerms->isNotEmpty()) {
            $this->info("[Info] Found " . $orphanPerms->count() . " orphan permissions (no roles assigned).");
            $risks++;
        }

        // 4. Check for Safe Mode configuration
        $protectedRoles = Config::get('entrust.protected_roles', []);
        if (empty($protectedRoles)) {
            $this->error("[Risk] No protected roles defined! Critical roles can be deleted.");
            $risks++;
        }

        $this->newLine();
        if ($risks === 0) {
            $this->info('Scan completed: No significant risks found. Your ACL setup is lean and secure.');
        } else {
            $this->info("Scan completed with {$risks} items to review.");
        }

        // 5. Check for Dormant Administrators (Heuristic)
        $this->newLine();
        $this->info("Running AI-Ready Heuristics...");

        $admins = $userClass::whereHas('roles', function ($q) use ($protectedRoles) {
            $q->whereIn('name', $protectedRoles);
        })->where('updated_at', '<', now()->subDays(30))->get();

        if ($admins->isNotEmpty()) {
            $this->warn("[Heuristic] Found " . $admins->count() . " administrators inactive for 30+ days.");
            foreach ($admins as $admin) {
                $this->line(" - Admin: {$admin->email} (Last Active: {$admin->updated_at})");
            }
            $risks++;
        }

        // 6. Check for Permission Hoarding (Too many direct permissions)
        $hoarders = $userClass::has('permissions', '>', 5)->get();
        if ($hoarders->isNotEmpty()) {
            $this->warn("[Heuristic] Found " . $hoarders->count() . " users with excessive direct permissions (>5). Use Roles!");
            $risks++;
        }
    }
}
