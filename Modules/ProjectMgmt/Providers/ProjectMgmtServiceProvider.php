<?php

namespace Modules\ProjectMgmt\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider do módulo ProjectMgmt.
 *
 * Modelado conforme Modules/TeamMcp/Providers/TeamMcpServiceProvider.php.
 * Rotas carregadas via start.php (ver module.json "files").
 */
class ProjectMgmtServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
    }

    public function register(): void
    {
        //
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('projectmgmt.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'projectmgmt'
        );
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/projectmgmt');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'projectmgmt');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'projectmgmt');
        }
    }

    public function provides(): array
    {
        return [];
    }
}
