<?php

namespace Modules\KB\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider do módulo KB (Knowledge Base).
 *
 * Modelado conforme Modules/Copiloto/Providers/CopilotoServiceProvider.php.
 * Rotas carregadas via start.php (ver module.json "files").
 *
 * Etapa 2 da modularização (split do Copiloto): /kb passa a viver fora do
 * módulo Copiloto, mas continua consumindo `mcp_memory_documents` (tabela
 * mantida pelo Copiloto até PR de schema tipado).
 */
class KBServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot do módulo.
     */
    public function boot(Router $router): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Sem bindings por enquanto — controller usa Eloquent direto contra
        // McpMemoryDocument (Modules\Copiloto\Entities\Mcp\McpMemoryDocument).
    }

    /**
     * Publica e merge do arquivo de config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('kb.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'kb'
        );
    }

    /**
     * Publica e registra as views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/kb');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(
            array_merge(array_map(function ($path) {
                return $path . '/modules/kb';
            }, \Config::get('view.paths')), [$sourcePath]),
            'kb'
        );
    }

    /**
     * Registra as traduções.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/kb');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'kb');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'kb');
        }
    }

    public function provides(): array
    {
        return [];
    }
}
