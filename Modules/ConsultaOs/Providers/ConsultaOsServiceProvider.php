<?php

namespace Modules\ConsultaOs\Providers;

use Illuminate\Support\ServiceProvider;

class ConsultaOsServiceProvider extends ServiceProvider
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
            __DIR__ . '/../Config/config.php' => config_path('consultaos.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'consultaos');
    }
}
