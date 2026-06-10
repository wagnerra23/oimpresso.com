<?php

namespace Modules\OficinaAuto\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Policies\ServiceOrderPolicy;
use Modules\OficinaAuto\Policies\VehiclePolicy;

/**
 * ServiceProvider Modules/OficinaAuto (ADR 0137 — qualified signal Vargas + Martinho).
 *
 * Vertical oficinas automotivas BR (CNAEs 4520/2212/4581). V0 em construção.
 *
 * Sprint 1 scaffold V0 (US-OFICINA-001): CRUD Vehicle + ServiceOrder + multi-tenant
 * Tier 0 obrigatório [ADR 0093]. Importer legacy Firebird (US-OFICINA-002) entra
 * Sprint 2+.
 *
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 * @see memory/requisitos/OficinaAuto/SPEC.md
 * @see memory/requisitos/Infra/RUNBOOK-criar-modulo.md
 */
class OficinaAutoServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $this->registerConfig();
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'oficina-auto');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerCommands();
        $this->registerPolicies();

        // ADR 0192 (extensão pra OficinaAuto vertical) — Auto-faturar OS → Venda derivada
        // quando ServiceOrder.status transiciona pra 'concluida' (terminal sucesso).
        // Multi-tenant Tier 0 (ADR 0093) preservado · idempotente via (business_id, os_ref="SO-{id}").
        ServiceOrder::observe(\Modules\OficinaAuto\Observers\ServiceOrderObserver::class);
    }

    /**
     * D8 Security Wave 15 — registra Policies multi-tenant (defense-in-depth).
     *
     * Global scope no Model + Spatie permission + Policy sameTenant guard.
     *
     * @see Modules\OficinaAuto\Policies\VehiclePolicy
     * @see Modules\OficinaAuto\Policies\ServiceOrderPolicy
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(ServiceOrder::class, ServiceOrderPolicy::class);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Commands artisan OficinaAuto (CYCLE-06 — cleanup pós-migração cliente legacy PR #555).
     *
     * - oficina:cleanup-migrated {biz} [--dry-run] [--detail]
     * - oficina:sanity-check {biz} [--detail]
     * - oficina:migration-report {biz} [--detail]
     * - oficina:import-firebird-martinho --business=N [--json=path] [--dry-run] (W27 G4 — esqueleto)
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\OficinaAuto\Console\Commands\OficinaAutoCleanupMigratedClientCommand::class,
                \Modules\OficinaAuto\Console\Commands\OficinaAutoSanityCheckCommand::class,
                \Modules\OficinaAuto\Console\Commands\OficinaAutoMigrationReportCommand::class,
                \Modules\OficinaAuto\Console\Commands\ImportFirebirdMartinhoCommand::class,
                \Modules\OficinaAuto\Console\Commands\OficinaBoardDemoCommand::class,
            ]);
        }
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('oficina-auto.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'oficina-auto');
    }
}
