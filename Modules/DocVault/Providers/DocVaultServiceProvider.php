<?php

namespace Modules\DocVault\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider do DocVault.
 *
 * Modelado conforme Modules/PontoWr2 (padrão UltimatePOS). Rotas vêm via
 * start.php (ver module.json "files").
 */
class DocVaultServiceProvider extends ServiceProvider
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
                \Modules\DocVault\Console\Commands\MigrateModuleCommand::class,
                \Modules\DocVault\Console\Commands\SyncPagesCommand::class,
                \Modules\DocVault\Console\Commands\ValidateCommand::class,
                \Modules\DocVault\Console\Commands\GenTestCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(
            \Modules\DocVault\Services\RequirementsFileReader::class
        );
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('docvault.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'docvault');
    }

    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/docvault');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([$sourcePath => $viewPath], 'views');

        $this->loadViewsFrom(
            array_merge(
                array_map(fn ($path) => $path . '/modules/docvault', \Config::get('view.paths')),
                [$sourcePath]
            ),
            'docvault'
        );
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/docvault');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'docvault');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'docvault');
        }
    }

    public function provides(): array
    {
        return [];
    }
}
