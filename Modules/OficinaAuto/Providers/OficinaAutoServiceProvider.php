<?php

namespace Modules\OficinaAuto\Providers;

use Illuminate\Support\ServiceProvider;

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
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('oficina-auto.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'oficina-auto');
    }
}
