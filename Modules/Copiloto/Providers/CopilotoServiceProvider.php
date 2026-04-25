<?php

namespace Modules\Copiloto\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Facades\Module;

/**
 * ServiceProvider do módulo Copiloto.
 *
 * Modelado conforme Modules/PontoWr2/Providers/PontoWr2ServiceProvider.php.
 * Rotas carregadas via start.php (ver module.json "files").
 */
class CopilotoServiceProvider extends ServiceProvider
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
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    /**
     * Register the service provider — binds + singletons.
     */
    public function register(): void
    {
        $this->app->singleton(\Modules\Copiloto\Services\SuggestionEngine::class);
        $this->app->singleton(\Modules\Copiloto\Services\ApuracaoService::class);
        $this->app->singleton(\Modules\Copiloto\Services\ContextSnapshotService::class);
        $this->app->singleton(\Modules\Copiloto\Services\AlertaService::class);

        // Adapter IA — ver adr/tech/0002
        $this->app->bind(
            \Modules\Copiloto\Contracts\AiAdapter::class,
            function () {
                $adapterMode = config('copiloto.ai_adapter', 'auto');

                if ($adapterMode === 'laravel_ai' || ($adapterMode === 'auto' && $this->laravelAiAvailable())) {
                    return $this->app->make(\Modules\Copiloto\Services\Ai\LaravelAiDriver::class);
                }

                return $this->app->make(\Modules\Copiloto\Services\Ai\OpenAiDirectDriver::class);
            }
        );
    }

    /**
     * Módulo LaravelAI instalado e ativo?
     */
    protected function laravelAiAvailable(): bool
    {
        try {
            $module = Module::find('LaravelAI');
            return $module && $module->isEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Publica e merge do arquivo de config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('copiloto.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'copiloto'
        );
    }

    /**
     * Publica e registra as views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/copiloto');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(
            array_merge(array_map(function ($path) {
                return $path . '/modules/copiloto';
            }, \Config::get('view.paths')), [$sourcePath]),
            'copiloto'
        );
    }

    /**
     * Registra as traduções.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/copiloto');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'copiloto');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'copiloto');
        }
    }

    public function provides(): array
    {
        return [];
    }
}
