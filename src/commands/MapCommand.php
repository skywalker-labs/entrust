<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class MapCommand extends Command
{
    protected $signature = 'skywalker:map';
    protected $description = 'Visualize the role and permission hierarchy';

    public function handle(): void
    {
        $this->info("Skywalker Role Hierarchy Map");
        $this->line("============================");
        $this->newLine();

        $roleClass = Config::get('entrust.role');
        $roles = $roleClass::whereNull('parent_id')->get();

        foreach ($roles as $role) {
            $this->renderRole($role, 0);
        }
    }

    protected function renderRole($role, int $level): void
    {
        $indent = str_repeat('  ', $level);
        $prefix = $level > 0 ? '└── ' : '⭐ ';

        $this->line("{$indent}{$prefix}<info>{$role->name}</info> (" . $role->perms()->count() . " perms)");

        foreach ($role->perms as $perm) {
            $this->line("{$indent}    ├─ <comment>#{$perm->name}</comment>");
        }

        foreach ($role->children as $child) {
            $this->renderRole($child, $level + 1);
        }
    }
}
