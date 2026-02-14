<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class ImportCommand extends Command
{
    protected $signature = 'skywalker:import {path=entrust_export.json} {--force : Overwrite existing items}';
    protected $description = 'Import roles and permissions from a JSON file';

    public function handle(): void
    {
        $path = $this->argument('path');
        if (!File::exists($path)) {
            $this->error("File not found: {$path}");
            return;
        }

        $data = json_decode(File::get($path), true);
        if (!$data) {
            $this->error("Invalid JSON data in {$path}");
            return;
        }

        $roleClass = Config::get('entrust.role');
        $permissionClass = Config::get('entrust.permission');
        $force = $this->option('force');

        $this->info("Importing permissions...");
        foreach ($data['permissions'] ?? [] as $pData) {
            $permissionClass::updateOrCreate(
                ['name' => $pData['name'], 'guard_name' => $pData['guard_name']],
                collect($pData)->except(['id', 'created_at', 'updated_at'])->toArray()
            );
        }

        $this->info("Importing roles and syncing permissions...");
        foreach ($data['roles'] ?? [] as $rData) {
            $role = $roleClass::updateOrCreate(
                ['name' => $rData['name'], 'guard_name' => $rData['guard_name']],
                collect($rData)->except(['id', 'perms', 'created_at', 'updated_at'])->toArray()
            );

            $permNames = collect($rData['perms'] ?? [])->pluck('name')->toArray();
            $permIds = $permissionClass::whereIn('name', $permNames)
                ->where('guard_name', $rData['guard_name'])
                ->pluck('id')
                ->toArray();

            $role->perms()->sync($permIds);
        }

        $this->info("Successfully imported roles and permissions from {$path}");
    }
}
