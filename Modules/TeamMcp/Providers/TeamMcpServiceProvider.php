<?php

namespace Modules\TeamMcp\Providers;

use Illuminate\Support\ServiceProvider;

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

        // Sem comandos proprios: os 4 foram pra Modules/Forja em 2026-07-31
        // ([W] "MCP vai para Forja") — identidade (team-mcp:seed-actors,
        // teammcp:token:rotate) e handoff (handoff:ingest, handoff:stale-alert).
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
