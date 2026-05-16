<?php

namespace Modules\SRS\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider do módulo SRS (ex-MemCofre, renomeado em Fase 3.7 PR-2).
 *
 * Modelado conforme Modules/Ponto (padrão UltimatePOS). Rotas vêm via
 * start.php (ver module.json "files").
 *
 * Note: config keys + URLs + lang dir mantêm prefixo `memcofre.*` por
 * compatibilidade (rename PHP-only — ver plano §4 erratum).
 */
class SrsServiceProvider extends ServiceProvider
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
                \Modules\SRS\Console\Commands\MigrateModuleCommand::class,
                \Modules\SRS\Console\Commands\SyncPagesCommand::class,
                \Modules\SRS\Console\Commands\ValidateCommand::class,
                \Modules\SRS\Console\Commands\GenTestCommand::class,
                \Modules\SRS\Console\Commands\SyncMemoriesCommand::class,
                \Modules\SRS\Console\Commands\AuditModuleCommand::class,
                \Modules\SRS\Console\Commands\InstallHooksCommand::class,
                \Modules\SRS\Console\Commands\SrsHealthCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(
            \Modules\SRS\Services\RequirementsFileReader::class
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
