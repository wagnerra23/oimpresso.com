<?php

namespace Modules\Governance\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Http\Middleware\ActionGate;
use Modules\Governance\Services\DriftCheckerRegistry;
use Modules\Forja\Services\ActorResolver;

class GovernanceServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerMigrations();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerDriftCheckers();
    }

    /**
     * Registra o path de migrations do módulo (padrão nWidart — 30 dos 33 módulos
     * já faziam isto; Governance era exceção não-intencional).
     *
     * **O bug que isto conserta (medido em prod 2026-08-08):** este SP NUNCA chamou
     * `loadMigrationsFrom`, então o `php artisan migrate --force` do deploy (path
     * default) **pulava** as 5 migrations de `Modules/Governance/`. Estado medido na
     * prod DB `u906587222_oimpresso`:
     *
     *   mcp_module_grades_history          AUSENTE
     *   mcp_scorecard_runs                 AUSENTE
     *   mcp_observability_spans            AUSENTE
     *   mcp_observability_aggregates_daily AUSENTE
     *   mcp_governance_initiatives         AUSENTE
     *   mcp_sdd_scorecard_history          EXISTE   ← única com row em `migrations`
     *                                                (batch 188, aplicada fora-de-banda
     *                                                por path único no pré-req do P06)
     *
     * Efeito visível: `module:grade-snapshot` (cron 06:05 BRT) morria todo dia com
     * `SQLSTATE[42S02] ... mcp_module_grades_history doesn't exist` — **120 ocorrências**
     * no `laravel.log` de prod — e a sparkline 7d nunca teve o que renderizar.
     *
     * Seguro por construção: as 5 migrations são guardadas por `Schema::hasTable()`,
     * então o próximo `migrate --force` cria as 4 ausentes e pula a que já existe.
     *
     * Precedente idêntico: `KBServiceProvider` (bug 2026-07-23, mesma causa, mesmo fix).
     *
     * @see Modules\Governance\Tests\Unit\GovernanceMigrationsRegisteredTest
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    /**
     * Auto-registra DriftCheckers do config/governance.php em DriftCheckerRegistry.
     * ADR 0216 §Decisão.
     */
    protected function registerDriftCheckers(): void
    {
        if (! config('governance.drift_framework_enabled', true)) {
            return;
        }

        $registry = $this->app->make(DriftCheckerRegistry::class);
        $classes = (array) config('governance.drift_checkers', []);

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                continue;
            }
            $checker = $this->app->make($class);
            if (! $checker instanceof DriftChecker) {
                continue;
            }
            if ($registry->has($checker->name())) {
                continue; // idempotente — boot pode ser chamado mais de 1× em testes
            }
            $registry->register($checker);
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Governance\Console\Commands\CharterAuditCommand::class,
                \Modules\Governance\Console\Commands\CharterHealthCommand::class,
                \Modules\Governance\Console\Commands\CharterMetricsCommand::class,
                \Modules\Governance\Console\Commands\GovernanceHealthCommand::class,
                \Modules\Governance\Console\Commands\ModuleGradeCommand::class,           // v3 (preserva — coexistência)
                \Modules\Governance\Console\Commands\ModuleGradeSnapshotCommand::class,
                \Modules\Governance\Console\Commands\ScorecardSnapshotCommand::class,
                \Modules\Governance\Console\Commands\SddScorecardSnapshotCommand::class,    // GT-G7 — snapshot diário scorecard SDD (ADR 0275)
                \Modules\Governance\Console\Commands\ObservabilityAggregateCommand::class,  // Wave 26 Agent 3 — ADR 0162
                \Modules\Governance\Console\Commands\ScorecardInitiativeSyncCommand::class, // Wave 28 Agent 1 — Initiatives Cortex-style
                \Modules\Governance\Console\Commands\DetectDriftCommand::class,             // SCOPE.md drift scan (Charter × filesystem)
                \Modules\Governance\Console\Commands\GovernanceAuditCommand::class,        // ADR 0216 — DriftChecker orchestrator
                \Modules\Governance\Console\Commands\GovernancaScorecardCommand::class,    // W28 — placar [CC]×Jana mecanizado (graduação de lições)
                \Modules\Governance\Console\Commands\CicloDiarioGovernancaCommand::class,  // ciclo diário — orquestra estado+frescor+inbox+digest (advisory)
                \Modules\Governance\Console\Commands\AdrReviewFlushCommand::class,         // ADR 0317 M3 — flush trimestral fila revisão de ADR (Checks O/R)
                \Modules\Governance\Console\Commands\UiCatalogGenerateCommand::class,      // gera memory/requisitos/<Mod>/UI-CATALOG.md — resgatado da depreciação do Modules/Admin (lá vivia SEM registro)
                \Modules\Governance\Console\Commands\RecordStagingFreshnessAlertCommand::class, // sink da sentinela de frescor do staging (host → mcp_alertas, ADR 0216)
                \Modules\Governance\Console\Commands\RecordRagasEvalAlertCommand::class, // sink do eval REAL da Jana medido no CT 100 (staging → mcp_alertas, ADR 0216)
                \Modules\Governance\Console\Commands\BladeMigrationSentinelCommand::class, // ADR 0277 — cobra a rota Blade→React (escala pro brief quando regride/estagna)
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(ActionGate::class, function ($app) {
            return new ActionGate($app->make(ActorResolver::class));
        });

        // ADR 0216 — Drift Framework registry singleton
        $this->app->singleton(DriftCheckerRegistry::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('governance.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'governance'
        );
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/governance');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'governance');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'governance');
        }
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('actiongate', ActionGate::class);
    }

    public function provides(): array
    {
        return [];
    }
}
