<?php

namespace Modules\Copiloto\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Copiloto\Drivers\Sql\SqlDriver;
use Modules\Copiloto\Events\CopilotoDesvioDetectado;
use Modules\Copiloto\Listeners\NotificarDesvioListener;
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

        // Eventos do módulo
        Event::listen(CopilotoDesvioDetectado::class, NotificarDesvioListener::class);
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

        // Drivers de apuração — ver adr/tech/0001
        $this->app->tag([SqlDriver::class], 'copiloto.drivers');

        // Adapter IA — verdade canônica em ADRs 0031/0032/0033/0034/0035
        $this->app->bind(
            \Modules\Copiloto\Contracts\AiAdapter::class,
            function () {
                $adapterMode = config('copiloto.ai_adapter', 'auto');

                // 'laravel_ai_sdk' (CANÔNICO) — pacote oficial laravel/ai (fev/2026)
                if ($adapterMode === 'laravel_ai_sdk' || ($adapterMode === 'auto' && $this->laravelAiSdkAvailable())) {
                    return $this->app->make(\Modules\Copiloto\Services\Ai\LaravelAiSdkDriver::class);
                }

                // 'openai_direct' (LEGADO/deprecated) — depende de openai-php/laravel não instalado
                return $this->app->make(\Modules\Copiloto\Services\Ai\OpenAiDirectDriver::class);
            }
        );

        // MemoriaContrato — verdade canônica ADR 0036 (Meilisearch first, Mem0 último)
        $this->app->bind(
            \Modules\Copiloto\Contracts\MemoriaContrato::class,
            function () {
                $driver = config('copiloto.memoria.driver', 'auto');

                // 'null' — dev / dry_run / CI (não chama rede)
                if ($driver === 'null' || config('copiloto.dry_run')) {
                    return $this->app->make(\Modules\Copiloto\Services\Memoria\NullMemoriaDriver::class);
                }

                // 'meilisearch' (CANÔNICO) — Scout + Meilisearch self-hosted
                if ($driver === 'meilisearch' || $driver === 'auto') {
                    return $this->app->make(\Modules\Copiloto\Services\Memoria\MeilisearchDriver::class);
                }

                // 'mem0_rest' (CONDICIONAL sprint 8+) — placeholder, não implementado ainda
                throw new \RuntimeException(
                    "Driver de memória '{$driver}' não implementado. ".
                    'Drivers válidos: meilisearch (default), null (dev), mem0_rest (sprint 8+).'
                );
            }
        );
    }

    /**
     * Pacote laravel/ai (Laravel AI SDK oficial) está instalado?
     * Detecta via class_exists no autoload, sem exigir publish de config.
     */
    protected function laravelAiSdkAvailable(): bool
    {
        return class_exists(\Laravel\Ai\AiManager::class);
    }

    /**
     * Módulo LaravelAI interno instalado e ativo? (legado, ainda referenciado por config)
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
