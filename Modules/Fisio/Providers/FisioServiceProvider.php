<?php

namespace Modules\Fisio\Providers;

use Illuminate\Support\ServiceProvider;

class FisioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        // US-FISIO-003: as 11 tabelas fisio_* (ADR 0437). Tabelas novas: sem efeito em empresa sem o pacote.
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('fisio.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'fisio');
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'fisio');
    }
}