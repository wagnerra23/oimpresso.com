<?php

namespace Modules\KB\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\KB\Entities\KbDecisionTreeStep;
use Modules\KB\Entities\KbNode;
use Modules\KB\Entities\KbNodeVersion;
use Modules\KB\Observers\KbDecisionTreeStepObserver;
use Modules\KB\Observers\KbNodeObserver;
use Modules\KB\Observers\KbNodeVersionObserver;

/**
 * ServiceProvider do módulo KB (Knowledge Base).
 *
 * **ONDA 1 (2026-05-15) — KB unificado como grafo (ADR 0149):**
 * Registra 3 Observers que enforcam invariantes Tier 0:
 *   - KbNodeObserver           → is_editable=false ⇒ body_blocks IS NULL + snapshot pre-update
 *   - KbNodeVersionObserver    → append-only (UPDATE/DELETE lançam Exception)
 *   - KbDecisionTreeStepObserver → branch yes/no tem exatamente 1 de (next OR fix)
 *
 * Modelado conforme Modules/Copiloto/Providers/CopilotoServiceProvider.php.
 * Rotas carregadas via start.php (ver module.json "files").
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
        $this->registerObservers();
        $this->registerCommands();
        $this->registerSchedule();
    }

    /**
     * Wave 27 §G2 — schedule weekly do kb:drift-detector (W23 KbDriftDetectorCommand).
     *
     * Frequência: domingo 03:00 BRT (baixa carga, pós-backup, pré-segunda).
     *
     * Estado-da-arte 2026 (Stack Pulsar, Oneuptime, Qdrant RAG Eval Guide):
     *   - daily = high-stakes apps (transações financeiras, médico)
     *   - weekly = governance/knowledge bases (drift artigo vs canon)
     *   - monthly = static reference docs
     *
     * Pattern Laravel 13+ callAfterResolving Schedule — isola schedule no provider
     * do módulo (não acopla app/Console/Kernel.php). Inspirado em ADR 0091 Daily Brief.
     *
     * Multi-tenant Tier 0: command iterate Business::active() internamente. Aqui só
     * dispatch superadmin biz=1 (proof-of-life cron). Per-business scheduling pode
     * ser adicionado quando mcp_briefs.cycle estabilizar (ver Wave 28+).
     *
     * onOneServer + withoutOverlapping: defesa contra:
     *   - Multi-server (CT 100 + Hostinger Hostinger scheduler simultâneo)
     *   - Run anterior >7 dias (drift catastrófico — exit 1 prolonga sessão)
     */
    protected function registerSchedule(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('kb:drift-detector --business-id=1')
                ->weeklyOn(0, '03:00')       // 0 = domingo
                ->timezone('America/Sao_Paulo')
                ->onOneServer()              // race-condition guard multi-server
                ->withoutOverlapping(60)     // 60min lock (drift detector típico <2min)
                ->runInBackground()
                ->name('kb:drift-detector-weekly')
                ->description('KB drift detector weekly Sun 03:00 BRT (Wave 27 §G2)');
        });
    }

    /**
     * Wave 23 §G4 — registra commands KB (kb:drift-detector + kb:reindex).
     * Wave 25 §G saturação D9 — adiciona kb:health-check.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\KB\Console\Commands\KbReindexCommand::class,
                \Modules\KB\Console\Commands\KbDriftDetectorCommand::class, // Wave 23 §G4 — drift artigo KB vs git log
                \Modules\KB\Console\Commands\KbHealthCommand::class,        // Wave 25 §G D9 — health-check RAG (corpus_size/bridge_freshness/retrieval_latency/editable_ratio)
                \Modules\KB\Console\Commands\KbClassifyCommand::class,       // 2026-07-17 — classifica kb_nodes via auto_match (dry-run default; resolve category_id NULL)
            ]);
        }
    }

    /**
     * Registra Observers que enforcam invariantes Tier 0.
     *
     * **CRÍTICO**: sem isto, kb_nodes bridge canônico pode gravar body_blocks
     * (viola invariant ADR 0061) e kb_node_versions pode ser editada/deletada
     * (viola append-only).
     */
    protected function registerObservers(): void
    {
        KbNode::observe(KbNodeObserver::class);
        KbNodeVersion::observe(KbNodeVersionObserver::class);
        KbDecisionTreeStep::observe(KbDecisionTreeStepObserver::class);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Wave 23 §G1 — KbBgeRerankerService singleton (BGE v2-m3 self-host CT 100).
        // Factory makeDefault() resolve endpoint via config('kb.bge.endpoint') + fallback RRF.
        $this->app->singleton(
            \Modules\KB\Services\KbBgeRerankerService::class,
            fn () => \Modules\KB\Services\KbBgeRerankerService::makeDefault()
        );
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
