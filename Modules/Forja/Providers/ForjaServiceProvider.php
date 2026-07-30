<?php

namespace Modules\Forja\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Forja\Console\Commands\ForjaHealthCommand;

/**
 * ServiceProvider do módulo Forja.
 *
 * Modelado conforme Modules/TeamMcp/Providers/TeamMcpServiceProvider.php.
 * Rotas carregadas via start.php (ver module.json "files").
 */
class ForjaServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();

        // Sem isto o comando existe no disco mas NUNCA chega ao Artisan — medido
        // 2026-07-28: `artisan project-mgmt:health` respondia "There are no commands
        // defined in the project-mgmt namespace" desde 2026-05-16 (Wave 17).
        // Padrão canônico: Modules/Vestuario/Providers/VestuarioServiceProvider.
        if ($this->app->runningInConsole()) {
            $this->commands([
                ForjaHealthCommand::class,
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
