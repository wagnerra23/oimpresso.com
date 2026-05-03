<?php

namespace Modules\ADS\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Modules\ADS\Services\RiskEngine;
use Modules\ADS\Services\PolicyEngine;
use Modules\ADS\Services\ConfidenceEngine;
use Modules\ADS\Services\DecisionRouter;
use Modules\ADS\Services\BrainBService;
use Modules\ADS\Http\Middleware\AdsApiAuth;
use Modules\ADS\Console\Commands\ProcessBrainBCommand;

class AdsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'ads');
        $this->registerMiddleware();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessBrainBCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(PolicyEngine::class);
        $this->app->singleton(RiskEngine::class);
        $this->app->singleton(ConfidenceEngine::class);
        $this->app->singleton(DecisionRouter::class);
        $this->app->singleton(BrainBService::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('ads.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'ads');
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('ads.api', AdsApiAuth::class);
    }
}
