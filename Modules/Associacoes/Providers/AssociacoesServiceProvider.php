<?php

namespace Modules\Associacoes\Providers;

use Illuminate\Support\ServiceProvider;

class AssociacoesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'associacoes');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'associacoes');
        // ADR 0451 onda 2: tabelas assoc_* novas e vazias — sem efeito em empresa sem o pacote.
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
