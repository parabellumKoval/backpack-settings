<?php

namespace Backpack\Settings;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    const CONFIG_PATH = __DIR__ . '/config/settings.php';

    public function boot()
    {
      // Translations
      $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'parabellumkoval');

      $this->publishes([
          __DIR__.'/resources/lang' => resource_path('lang/vendor/parabellumkoval'),
      ], 'trans');
    
	    // Migrations
	    $this->loadMigrationsFrom(__DIR__.'/database/migrations');
  
      $this->publishes([
          __DIR__.'/database/migrations' => resource_path('database/migrations'),
      ], 'migrations');
	    
	    // Routes
    	$this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');
    	$this->loadRoutesFrom(__DIR__.'/routes/api/settings.php');
  
      $this->publishes([
          __DIR__.'/routes/backpack/routes.php' => resource_path('/routes/backpack/settings/backpack.php'),
          __DIR__.'/routes/api/settings.php' => resource_path('/routes/backpack/settings/settings.php'),
      ], 'routes');

		  // Config
      $this->publishes([
        self::CONFIG_PATH => config_path('/parabellumkoval/settings.php'),
      ], 'config');
      
      // Views
      $this->loadViewsFrom(__DIR__.'/resources/views/vendor/backpack', 'backpack-settings');
      
      $this->publishes([
          __DIR__.'/resources/views' => resource_path('views'),
      ], 'views');

      // stub
      $this->publishes([
        __DIR__.'/app/Http/Controllers/Admin/Stubs' => base_path('app/Http/Controllers/Admin/Crud'),
      ], 'stub');

      // template
      $this->publishes([
        __DIR__.'/app/SettingsTemplates' => base_path('app/SettingsTemplates'),
      ], 'temps');

      // Seeders
      $this->publishes([
        __DIR__.'/database/seeders' => base_path('database/seeders'),
      ], 'seeders');

    }

    public function register()
    {
      // Apply package local config
      $this->mergeConfigFrom(__DIR__.'/config/settings.php', 'parabellumkoval.settings');
    }
}
