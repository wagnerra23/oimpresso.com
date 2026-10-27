<?php

namespace Modules\Marketplaces\Providers;

use Illuminate\Support\ServiceProvider;

class MarketplacesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'marketplaces');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'marketplaces');
        // US-MKT-001: tabelas mkt_* novas e vazias — sem efeito em empresa sem o pacote.
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
