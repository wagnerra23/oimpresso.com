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
        // US-FISIO-004: semeia a biblioteca global (idempotente; também roda no Install 1-clique).
        $this->commands([\Modules\Fisio\Console\SemearBibliotecaCommand::class]);
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