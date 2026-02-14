<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class ExportCommand extends Command
{
    protected $signature = 'skywalker:export {path=entrust_export.json}';
    protected $description = 'Export roles and permissions to a JSON file';

    public function handle(): void
    {
        $roleClass = Config::get('entrust.role');
        $permissionClass = Config::get('entrust.permission');

        $data = [
            'roles' => $roleClass::with('perms')->get()->toArray(),
            'permissions' => $permissionClass::all()->toArray(),
        ];

        $path = $this->argument('path');
        File::put($path, json_encode($data, JSON_PRETTY_PRINT));

        $this->info("Successfully exported roles and permissions to {$path}");
    }
}
