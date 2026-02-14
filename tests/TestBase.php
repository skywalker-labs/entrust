<?php

namespace Skywalker\Entrust\Test;

use Orchestra\Testbench\TestCase;
use Skywalker\Entrust\EntrustServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

abstract class TestBase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [EntrustServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('entrust.role', 'Skywalker\Entrust\EntrustRole');
        $app['config']->set('entrust.permission', 'Skywalker\Entrust\EntrustPermission');
        $app['config']->set('auth.providers.users.model', 'Skywalker\Entrust\Test\Stubs\User');
    }

    protected function setUpDatabase()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
            $table->timestamps();
        });

        // Run the package migration
        // We can't easily run the artisan command because we need to stub the view or similar.
        // So we'll define the schema manually for tests here, matching the latest migration template.

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->json('access_rules')->nullable();
            $table->integer('rate_limit')->nullable()->default(60);
            $table->boolean('is_protected')->default(false);
            $table->unsignedInteger('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('roles')->onDelete('set null');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('role_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unique(['user_id', 'role_id', 'team_id']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->string('guard_name')->default('web');
            $table->json('depends_on')->nullable();
            $table->json('context_rules')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->boolean('is_denied')->default(false);
            $table->unique(['user_id', 'permission_id', 'team_id']);
        });

        Schema::create('skywalker_access_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('type');
            $table->string('requested_item');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
}
