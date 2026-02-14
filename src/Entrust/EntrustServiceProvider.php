<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Skywalker\Support\Providers\PackageServiceProvider;

class EntrustServiceProvider extends PackageServiceProvider
{
    protected string $vendor = 'skywalker-labs';

    protected string $package = 'entrust';

    /**
     * Boot the service provider.
     */
    public function boot(): void
    {
        parent::boot();

        // Register commands
        $this->commands([
            'command.entrust.migration',
            'command.skywalker.sync',
            'command.skywalker.export',
            'command.skywalker.import',
            'command.skywalker.ui',
            'command.skywalker.trace',
            'command.skywalker.cleanup',
            'command.skywalker.scan',
            'command.skywalker.map',
            'command.skywalker.rollback',
            'command.skywalker.approve',
        ]);

        // Register blade directives
        $this->registerBladeDirectives();

        // Toolkit handles config publishing via $this->publishAll() if needed,
        // but let's keep it explicit if we want standard Laravel feel or use toolkit's helper
        $this->publishes([
            __DIR__ . '/../config/config.php' => $this->app->make('path.config') . DIRECTORY_SEPARATOR . 'entrust.php',
        ]);

        $this->bootGates();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->registerEntrust();

        $this->registerArtisanCommands();

        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php',
            'entrust'
        );
    }

    /**
     * Register the blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        if (!class_exists('\Blade')) return;

        // Call to Entrust::hasRole
        Blade::directive('role', function ($expression) {
            return "<?php if (\\Entrust::hasRole({$expression})) : ?>";
        });

        Blade::directive('endrole', function ($expression) {
            return "<?php endif; // Entrust::hasRole ?>";
        });

        // Call to Entrust::can
        Blade::directive('permission', function ($expression) {
            return "<?php if (\\Entrust::can({$expression})) : ?>";
        });

        Blade::directive('endpermission', function ($expression) {
            return "<?php endif; // Entrust::can ?>";
        });

        // Call to Entrust::ability
        Blade::directive('ability', function ($expression) {
            return "<?php if (\\Entrust::ability({$expression})) : ?>";
        });

        Blade::directive('endability', function ($expression) {
            return "<?php endif; // Entrust::ability ?>";
        });
    }

    /**
     * Automatically register all database permissions as Laravel Gates.
     */
    protected function bootGates(): void
    {
        if (!$this->app->runningInConsole() || $this->app->runningUnitTests()) {
            $this->app->booted(function ($app) {
                try {
                    $permissionClass = Config::get('entrust.permission');
                    if (class_exists($permissionClass)) {
                        $permissions = $permissionClass::all();
                        foreach ($permissions as $permission) {
                            Gate::define($permission->name, function ($user) use ($permission) {
                                return method_exists($user, 'can') ? $user->can($permission->name, false, $permission->guard_name) : false;
                            });
                        }
                    }
                } catch (\Exception $e) {
                    // Fail silently if table doesn't exist yet
                }
            });
        }
    }

    /**
     * The application instance.
     */
    protected \Illuminate\Contracts\Container\Container $app;

    /**
     * Register the application bindings.
     */
    private function registerEntrust(): void
    {
        $this->app->bind('entrust', function ($app) {
            return new Entrust($app);
        });

        $this->app->alias('entrust', Entrust::class);
    }

    /**
     * Register the artisan commands.
     */
    private function registerArtisanCommands(): void
    {
        $this->app->singleton('command.entrust.migration', function ($app) {
            return new MigrationCommand();
        });

        $this->app->singleton('command.skywalker.sync', function ($app) {
            return new SyncCommand();
        });

        $this->app->singleton('command.skywalker.export', function ($app) {
            return new ExportCommand();
        });

        $this->app->singleton('command.skywalker.import', function ($app) {
            return new ImportCommand();
        });

        $this->app->singleton('command.skywalker.ui', function ($app) {
            return new UIScaffoldCommand();
        });

        $this->app->singleton('command.skywalker.trace', function ($app) {
            return new TraceCommand();
        });

        $this->app->singleton('command.skywalker.cleanup', function ($app) {
            return new CleanupCommand();
        });

        $this->app->singleton('command.skywalker.scan', function ($app) {
            return new ScannerCommand();
        });

        $this->app->singleton('command.skywalker.map', function ($app) {
            return new MapCommand();
        });

        $this->app->singleton('command.skywalker.rollback', function ($app) {
            return new RollbackCommand();
        });

        $this->app->singleton('command.skywalker.approve', function ($app) {
            return new ApproveCommand();
        });
    }

    /**
     * Get the services provided.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'command.entrust.migration',
            'command.skywalker.sync',
            'command.skywalker.export',
            'command.skywalker.import',
            'command.skywalker.ui',
            'command.skywalker.trace',
            'command.skywalker.cleanup',
            'command.skywalker.scan',
            'command.skywalker.map',
            'command.skywalker.rollback',
            'command.skywalker.approve',
        ];
    }
}
