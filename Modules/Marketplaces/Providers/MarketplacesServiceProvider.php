<?php

namespace Modules\Marketplaces\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Marketplaces\Console\RenovarTokensCommand;

class MarketplacesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'marketplaces');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'marketplaces');
        // US-MKT-001: tabelas mkt_* novas e vazias — sem efeito em empresa sem o pacote.
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // US-MKT-003: o access token do ML vale ~6 h — renovação de hora em hora com folga de 1 h.
        $this->commands([RenovarTokensCommand::class]);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('marketplaces:renovar-tokens')->hourly()->withoutOverlapping();
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
