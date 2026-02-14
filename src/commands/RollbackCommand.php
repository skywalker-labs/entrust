<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RollbackCommand extends Command
{
    protected $signature = 'skywalker:rollback {--backup : Create a new snapshot} {--restore= : Restore a snapshot by filename} {--list : List available snapshots}';
    protected $description = 'Manage ACL state snapshots and rollbacks';

    public function handle(): void
    {
        if ($this->option('backup')) {
            $this->createSnapshot();
            return;
        }

        if ($this->option('list')) {
            $this->listSnapshots();
            return;
        }

        if ($restoreFile = $this->option('restore')) {
            $this->restoreSnapshot($restoreFile);
            return;
        }

        $this->warn("Please specify an action: --backup, --list, or --restore={file}");
    }

    protected function createSnapshot(): void
    {
        $this->info("Creating ACL state snapshot...");

        $state = [
            'roles' => DB::table(Config::get('entrust.roles_table'))->get()->toArray(),
            'permissions' => DB::table(Config::get('entrust.permissions_table'))->get()->toArray(),
            'permission_role' => DB::table(Config::get('entrust.permission_role_table'))->get()->toArray(),
            'permission_user' => DB::table('permission_user')->get()->toArray(),
            'role_user' => DB::table(Config::get('entrust.role_user_table'))->get()->toArray(),
            'timestamp' => \Carbon\Carbon::now()->toIso8601String(),
        ];

        $filename = 'skywalker_snapshot_' . \Carbon\Carbon::now()->format('Y-m-d_H-i-s') . '.json';
        Storage::put('skywalker/' . $filename, json_encode($state, JSON_PRETTY_PRINT));

        $this->info("Snapshot created successfully: <info>{$filename}</info>");
    }

    protected function listSnapshots(): void
    {
        $files = Storage::files('skywalker');
        if (empty($files)) {
            $this->info("No snapshots found.");
            return;
        }

        $this->info("Available Snapshots:");
        foreach ($files as $file) {
            $this->line(" - " . basename($file));
        }
    }

    protected function restoreSnapshot(string $filename): void
    {
        $path = 'skywalker/' . $filename;
        if (!Storage::exists($path)) {
            $this->error("Snapshot file not found: {$filename}");
            return;
        }

        if (!$this->confirm("Are you sure you want to restore this snapshot? This will OVERWRITE current roles and permissions linkage.")) {
            return;
        }

        $this->info("Restoring snapshot...");
        $state = json_decode(Storage::get($path), true);

        DB::transaction(function () use ($state) {
            // Truncate existing linkage tables
            DB::table(Config::get('entrust.permission_role_table'))->truncate();
            DB::table('permission_user')->truncate();
            DB::table(Config::get('entrust.role_user_table'))->truncate();

            // Restore from state
            foreach ($state['permission_role'] as $row) {
                DB::table(Config::get('entrust.permission_role_table'))->insert((array)$row);
            }
            foreach ($state['permission_user'] as $row) {
                DB::table('permission_user')->insert((array)$row);
            }
            foreach ($state['role_user'] as $row) {
                DB::table(Config::get('entrust.role_user_table'))->insert((array)$row);
            }
        });

        $this->info("Rollback successful. System state restored to " . $state['timestamp']);
    }
}
