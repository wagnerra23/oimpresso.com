<?php

namespace Modules\MemCofre\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider do MemCofre.
 *
 * Modelado conforme Modules/PontoWr2 (padrão UltimatePOS). Rotas vêm via
 * start.php (ver module.json "files").
 */
class MemCofreServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(Router $router): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerTranslations();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\MemCofre\Console\Commands\MigrateModuleCommand::class,
                \Modules\MemCofre\Console\Commands\SyncPagesCommand::class,
                \Modules\MemCofre\Console\Commands\ValidateCommand::class,
                \Modules\MemCofre\Console\Commands\GenTestCommand::class,
                \Modules\MemCofre\Console\Commands\SyncMemoriesCommand::class,
                \Modules\MemCofre\Console\Commands\AuditModuleCommand::class,
                \Modules\MemCofre\Console\Commands\InstallHooksCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(
            \Modules\MemCofre\Services\RequirementsFileReader::class
        );
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('memcofre.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'memcofre');
    }

    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/memcofre');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([$sourcePath => $viewPath], 'views');

        $this->loadViewsFrom(
            array_merge(
                array_map(fn ($path) => $path . '/modules/memcofre', \Config::get('view.paths')),
                [$sourcePath]
            ),
            'memcofre'
        );
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/memcofre');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'memcofre');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'memcofre');
        }
    }

    public function provides(): array
    {
        return [];
    }
}
