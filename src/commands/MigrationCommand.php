<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class MigrationCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'entrust:migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a migration following the Entrust specifications.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->laravel->view->addNamespace('entrust', substr(__DIR__, 0, -8) . 'views');

        $rolesTable          = Config::get('entrust.roles_table');
        $roleUserTable       = Config::get('entrust.role_user_table');
        $permissionsTable    = Config::get('entrust.permissions_table');
        $permissionRoleTable = Config::get('entrust.permission_role_table');

        $this->line('');
        $this->info("Tables: $rolesTable, $roleUserTable, $permissionsTable, $permissionRoleTable");

        $message = "A migration that creates '$rolesTable', '$roleUserTable', '$permissionsTable', '$permissionRoleTable'" .
            " tables will be created in database/migrations directory";

        $this->comment($message);
        $this->line('');

        if ($this->confirm('Proceed with the migration creation?', true)) {
            $this->line('');
            $this->info('Creating migration...');

            if ($this->createMigration($rolesTable, $roleUserTable, $permissionsTable, $permissionRoleTable)) {
                $this->info('Migration successfully created!');
            } else {
                $this->error(
                    "Couldn't create migration.\n Check the write permissions" .
                        " within the database/migrations directory."
                );
            }

            $this->line('');
        }
    }

    /**
     * Create the migration.
     */
    protected function createMigration(string $rolesTable, string $roleUserTable, string $permissionsTable, string $permissionRoleTable): bool
    {
        $migrationFile = $this->laravel->databasePath() . '/migrations/' . date('Y_m_d_His') . '_entrust_setup_tables.php';

        /** @var class-string $userModelName */
        $userModelName = Config::get('auth.providers.users.model');
        $userModel = new $userModelName();
        $usersTable = $userModel->getTable();
        $userKeyName = $userModel->getKeyName();

        $data = compact('rolesTable', 'roleUserTable', 'permissionsTable', 'permissionRoleTable', 'usersTable', 'userKeyName');

        $output = $this->laravel->view->make('entrust::generators.migration')->with($data)->render();

        if (!file_exists($migrationFile) && $fs = fopen($migrationFile, 'x')) {
            fwrite($fs, $output);
            fclose($fs);
            return true;
        }

        return false;
    }
}
