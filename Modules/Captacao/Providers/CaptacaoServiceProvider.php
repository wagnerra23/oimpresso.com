<?php

namespace Modules\Captacao\Providers;

use Illuminate\Support\ServiceProvider;

class CaptacaoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'captacao');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'captacao');
        // ADR 0452 onda 1: tabelas captacao_* novas e vazias — sem efeito em empresa sem o pacote.
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
