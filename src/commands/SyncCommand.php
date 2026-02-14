<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class SyncCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'skywalker:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync roles and permissions from config to database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $syncConfig = Config::get('entrust.sync');

        if (!$syncConfig) {
            $this->error('No sync configuration found in entrust.php');
            return;
        }

        $permissionClass = Config::get('entrust.permission');
        $roleClass = Config::get('entrust.role');

        $this->info("Syncing permissions...");
        $permissions = $syncConfig['permissions'] ?? [];
        foreach ($permissions as $name => $details) {
            // Default guard to web if not specified in details or logic
            $guard = $details['guard_name'] ?? 'web';
            $permissionClass::updateOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                $details
            );
        }

        $this->info("Syncing roles and their permissions...");
        $roles = $syncConfig['roles'] ?? [];
        foreach ($roles as $name => $details) {
            $rolePermissions = $details['permissions'] ?? [];
            $guard = $details['guard_name'] ?? 'web';
            unset($details['permissions']);

            $role = $roleClass::updateOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                $details
            );

            if (!empty($rolePermissions)) {
                $permissionIds = $permissionClass::whereIn('name', $rolePermissions)
                    ->where('guard_name', $guard)
                    ->pluck('id')
                    ->toArray();
                $role->perms()->sync($permissionIds);
            }
        }

        $this->info('Sync completed successfully!');
    }
}
