<?php

namespace Modules\Governance\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Http\Middleware\ActionGate;
use Modules\Governance\Services\DriftCheckerRegistry;
use Modules\TeamMcp\Services\ActorResolver;

class GovernanceServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerDriftCheckers();
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
                \Modules\Governance\Console\Commands\ModuleGradeV4Command::class,         // v4 (Wave 21 — scoped scorecards por bucket)
                \Modules\Governance\Console\Commands\ModuleGradeSnapshotCommand::class,
                \Modules\Governance\Console\Commands\ScorecardSnapshotCommand::class,
                \Modules\Governance\Console\Commands\SddScorecardSnapshotCommand::class,    // GT-G7 — snapshot diário scorecard SDD (ADR 0275)
                \Modules\Governance\Console\Commands\ObservabilityAggregateCommand::class,  // Wave 26 Agent 3 — ADR 0162
                \Modules\Governance\Console\Commands\ScorecardInitiativeSyncCommand::class, // Wave 28 Agent 1 — Initiatives Cortex-style
                \Modules\Governance\Console\Commands\DetectDriftCommand::class,             // SCOPE.md drift scan (Charter × filesystem)
                \Modules\Governance\Console\Commands\GovernanceAuditCommand::class,        // ADR 0216 — DriftChecker orchestrator
                \Modules\Governance\Console\Commands\GovernancaScorecardCommand::class,    // W28 — placar [CC]×Jana mecanizado (graduação de lições)
                \Modules\Governance\Console\Commands\CicloDiarioGovernancaCommand::class,  // ciclo diário — orquestra estado+frescor+inbox+digest (advisory)
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
