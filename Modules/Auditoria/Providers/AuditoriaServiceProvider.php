<?php

namespace Modules\Auditoria\Providers;

use Illuminate\Support\ServiceProvider;

class AuditoriaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('auditoria.php'),
            __DIR__.'/../Config/retention.php' => config_path('auditoria/retention.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'auditoria');
        $this->mergeConfigFrom(__DIR__.'/../Config/retention.php', 'auditoria.retention');
    }
}
