<?php

namespace Modules\Jana\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Jana\Drivers\Sql\SqlDriver;
use Modules\Jana\Events\CopilotoDesvioDetectado;
use Modules\Jana\Listeners\NotificarDesvioListener;
use Nwidart\Modules\Facades\Module;

/**
 * ServiceProvider do módulo Jana (ex-Copiloto, renomeado em Fase 3.7 PR-2).
 *
 * Modelado conforme Modules/Ponto/Providers/PontoServiceProvider.php.
 * Rotas carregadas via start.php (ver module.json "files").
 *
 * Note: config keys, log channels, URLs, permissions e Pages React mantêm
 * prefixo `copiloto.*` por compatibilidade (rename PHP-only — ver plano §4 erratum).
 */
class JanaServiceProvider extends ServiceProvider
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

        // MEM-MCP-1.b (ADR 0053) — middleware de auth do MCP server
        $router->aliasMiddleware('mcp.auth', \Modules\Jana\Http\Middleware\McpAuthMiddleware::class);

        // Comandos artisan
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Jana\Console\Commands\ApurarMetricasCommand::class,    // MEM-MET-2
                \Modules\Jana\Console\Commands\AvaliarGabaritoCommand::class,   // MEM-EVAL-1
                \Modules\Jana\Console\Commands\BackfillFatosCommand::class,     // MEM-EVAL-2
                \Modules\Jana\Console\Commands\McpSystemTokenCommand::class,    // MEM-MEM-MCP-1
                \Modules\Jana\Console\Commands\McpSyncMemoryCommand::class,     // MEM-MCP-1.a
                \Modules\Jana\Console\Commands\McpTokenGerarCommand::class,     // MEM-MCP-1.b
                \Modules\Jana\Console\Commands\McpAdrMigrarFrontmatterCommand::class, // MEM-KB-3 / F1
                \Modules\Jana\Console\Commands\SeedAdrsCommand::class,          // MEM-MULTI-1
                \Modules\Jana\Console\Commands\CleanupMemoriaCommand::class,   // MEM-FASE8
                \Modules\Jana\Console\Commands\SinteseSemanalCommand::class,   // MemoriaAutonoma F1
                \Modules\Jana\Console\Commands\McpTasksSyncCommand::class,     // TaskRegistry F0
                \Modules\Jana\Console\Commands\BackfillTasksFromMarkdownCommand::class, // ADR 0070 — backfill 1× CURRENT.md/TASKS.md
                \Modules\Jana\Console\Commands\McpSkillsImportFromGitCommand::class, // ADR 0076 Fase 1
                \Modules\Jana\Console\Commands\HealthCheckCommand::class,      // sentinela operacional 5 checks
                \Modules\Jana\Console\Commands\SystemAuditCommand::class,      // ADR 0133 — 5 audits Constituição v2 (observ/evals/ADR-stale/cost/coverage)
                \Modules\Jana\Console\Commands\McpTasksHealthCheckCommand::class, // Bug #4 BUGS-MCP-SYNC-2026-05-13 — staleness detection
                \Modules\Jana\Console\Commands\JanaBacklinksSweepCommand::class, // Gap G5 P1 auditoria 2026-05-13 — backlinks ADR↔SPEC sweep
                \Modules\Jana\Console\Commands\JanaRagasEvalCommand::class,    // ADR 0037 §GAP-2 — RAGAS gate (faithfulness/relevancy/precision/recall)
            ]);
        }
    }

    /**
     * Register the service provider — binds + singletons.
     */
    public function register(): void
    {
        $this->app->singleton(\Modules\Jana\Services\SuggestionEngine::class);
        $this->app->singleton(\Modules\Jana\Services\ApuracaoService::class);
        $this->app->singleton(\Modules\Jana\Services\ContextSnapshotService::class);
        $this->app->singleton(\Modules\Jana\Services\AlertaService::class);

        // Drivers de apuração — ver adr/tech/0001
        $this->app->tag([SqlDriver::class], 'copiloto.drivers');

        // Adapter IA — verdade canônica em ADRs 0031/0032/0033/0034/0035
        $this->app->bind(
            \Modules\Jana\Contracts\AiAdapter::class,
            function () {
                $adapterMode = config('copiloto.ai_adapter', 'auto');

                // 'laravel_ai_sdk' (CANÔNICO) — pacote oficial laravel/ai (fev/2026)
                if ($adapterMode === 'laravel_ai_sdk' || ($adapterMode === 'auto' && $this->laravelAiSdkAvailable())) {
                    return $this->app->make(\Modules\Jana\Services\Ai\LaravelAiSdkDriver::class);
                }

                // 'openai_direct' (LEGADO/deprecated) — depende de openai-php/laravel não instalado
                return $this->app->make(\Modules\Jana\Services\Ai\OpenAiDirectDriver::class);
            }
        );

        // MemoriaContrato — verdade canônica ADR 0036 (Meilisearch first, Mem0 último)
        $this->app->bind(
            \Modules\Jana\Contracts\MemoriaContrato::class,
            function () {
                $driver = config('copiloto.memoria.driver', 'auto');

                // 'null' — dev / dry_run / CI (não chama rede)
                if ($driver === 'null' || config('copiloto.dry_run')) {
                    return $this->app->make(\Modules\Jana\Services\Memoria\NullMemoriaDriver::class);
                }

                // 'meilisearch' (CANÔNICO até abr/2026) — Scout + Meilisearch self-hosted
                if ($driver === 'meilisearch' || $driver === 'auto') {
                    return $this->app->make(\Modules\Jana\Services\Memoria\MeilisearchDriver::class);
                }

                // 'mcp' (NOVO ADR 0056) — Copiloto chat consome MCP server.
                // Fallback automático: se MCP indisponível, usa MeilisearchDriver direto.
                if ($driver === 'mcp') {
                    $fallback = $this->app->make(\Modules\Jana\Services\Memoria\MeilisearchDriver::class);
                    return new \Modules\Jana\Services\Memoria\McpMemoriaDriver($fallback);
                }

                // 'mem0_rest' (CONDICIONAL sprint 8+) — placeholder, não implementado ainda
                throw new \RuntimeException(
                    "Driver de memória '{$driver}' não implementado. ".
                    'Drivers válidos: meilisearch (default), mcp (ADR 0056), null (dev), mem0_rest (futuro).'
                );
            }
        );

        // Reranker canônico (GAP-A — AUDITORIA 2026-05-13 §5 G3)
        // Drivers: rrf (default), llm (LLM-as-judge), null (passthrough).
        $this->app->bind(
            \Modules\Jana\Services\Retrieval\Reranker::class,
            function () {
                $enabled = (bool) config('copiloto.reranker.enabled', true);
                $driver  = (string) config('copiloto.reranker.driver', 'rrf');

                if (! $enabled || $driver === 'null') {
                    return $this->app->make(\Modules\Jana\Services\Retrieval\NullReranker::class);
                }

                if ($driver === 'llm') {
                    return new \Modules\Jana\Services\Retrieval\LlmRerankerAdapter(
                        $this->app->make(\Modules\Jana\Services\Memoria\LlmReranker::class)
                    );
                }

                // rrf (default MVP)
                return $this->app->make(\Modules\Jana\Services\Retrieval\RrfReranker::class);
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
