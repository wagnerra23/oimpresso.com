<?php

namespace Modules\TeamMcp\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\TeamMcp\Console\Commands\RotateTokenCommand;
use Modules\TeamMcp\Console\Commands\SeedActorsCommand;

/**
 * ServiceProvider do módulo TeamMcp.
 *
 * Modelado conforme Modules/Copiloto/Providers/CopilotoServiceProvider.php.
 * Rotas carregadas via start.php (ver module.json "files").
 *
 * Commands artisan registrados em runningInConsole():
 *   - team-mcp:seed-actors — popular 5 manifests Identity Mesh (ADR 0081)
 *   - teammcp:token:rotate — G3 FICHA W22 self-service token rotation (Wave 25)
 */
class TeamMcpServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedActorsCommand::class,
                RotateTokenCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        //
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('teammcp.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'teammcp'
        );
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/teammcp');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'teammcp');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'teammcp');
        }
    }

    public function provides(): array
    {
        return [];
    }
}
