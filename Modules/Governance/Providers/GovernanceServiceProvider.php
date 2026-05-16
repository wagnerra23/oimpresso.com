<?php

namespace Modules\Governance\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Governance\Http\Middleware\ActionGate;
use Modules\TeamMcp\Services\ActorResolver;

class GovernanceServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerMiddleware();
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Governance\Console\Commands\CharterAuditCommand::class,
                \Modules\Governance\Console\Commands\CharterHealthCommand::class,
                \Modules\Governance\Console\Commands\CharterMetricsCommand::class,
                \Modules\Governance\Console\Commands\ModuleGradeCommand::class,
                \Modules\Governance\Console\Commands\ModuleGradeSnapshotCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(ActionGate::class, function ($app) {
            return new ActionGate($app->make(ActorResolver::class));
        });
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('governance.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'governance'
        );
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/governance');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'governance');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'governance');
        }
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('actiongate', ActionGate::class);
    }

    public function provides(): array
    {
        return [];
    }
}
