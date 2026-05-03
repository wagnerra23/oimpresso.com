<?php

namespace Modules\ADS\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ADS\Services\RiskEngine;
use Modules\ADS\Services\PolicyEngine;
use Modules\ADS\Services\ConfidenceEngine;
use Modules\ADS\Services\DecisionRouter;

class AdsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->singleton(PolicyEngine::class);
        $this->app->singleton(RiskEngine::class);
        $this->app->singleton(ConfidenceEngine::class);
        $this->app->singleton(DecisionRouter::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('ads.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'ads');
    }
}
