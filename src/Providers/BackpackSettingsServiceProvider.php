<?php

namespace Backpack\Settings\Providers;

use Illuminate\Support\ServiceProvider;
use Backpack\Settings\Services\SettingsManager;
use Backpack\Settings\Drivers\ConfigDriver;
use Backpack\Settings\Drivers\DatabaseDriver;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Contracts\SettingsRegistrarInterface;

class BackpackSettingsServiceProvider extends ServiceProvider
{
    public function register()
    {

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // merge config
        $this->mergeConfigFrom(__DIR__.'/../../config/backpack-settings.php', 'backpack-settings');

        // Bind registry
        $this->app->singleton('backpack.settings.registry', function () {
            return new Registry();
        });

        // Bind drivers
        $this->app->singleton('backpack.settings.driver.config', function ($app) {
            return new ConfigDriver($app['config']);
        });

        $this->app->singleton('backpack.settings.driver.database', function ($app) {
            return new DatabaseDriver($app['db']->connection(), config('backpack-settings.table'));
        });

        // Bind manager
        $this->app->singleton('backpack.settings', function ($app) {
            $drivers = [];
            foreach (config('backpack-settings.drivers', ['database','config']) as $name) {
                $drivers[$name] = $app->make('backpack.settings.driver.'.$name);
            }
            return new SettingsManager($app['cache.store'] ?? $app['cache'], $drivers);
        });
    }

    public function boot()
    {
        // routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/backpack-settings.php');

        // views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', config('backpack-settings.view_namespace', 'backpack-settings'));

        // publish
        $this->publishes([
            __DIR__.'/../../config/backpack-settings.php' => config_path('backpack-settings.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/'.config('backpack-settings.view_namespace', 'backpack-settings')),
        ], 'views');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'migrations');

        // Auto-register registrars from config
        $registry = $this->app->make('backpack.settings.registry');
        foreach (config('backpack-settings.registrars', []) as $registrarClass) {
            if (class_exists($registrarClass)) {
                $registrar = $this->app->make($registrarClass);
                if ($registrar instanceof SettingsRegistrarInterface) {
                    $registrar->register($registry);
                }
            }
        }
    }
}
