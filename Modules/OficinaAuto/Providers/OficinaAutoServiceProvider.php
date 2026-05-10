<?php

namespace Modules\OficinaAuto\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider Modules/OficinaAuto (ADR 0121 §P7).
 *
 * Vertical oficinas automotivas BR (CNAE 4520). Estado feature-wish — aguarda
 * sinal qualificado [ADR 0105]: candidato Martinho Caçambas a confirmar.
 *
 * Sprint 1 scaffold + RepairSettingsSeeder reusa DEFAULT Modules/Repair
 * (Box B1-B4 + Elevador E1-E2 + executor=Mecânico) — vocabulário automotivo
 * sai dos defaults sem custom seeder.
 *
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 * @see memory/requisitos/OficinaAuto/SPEC.md
 */
class OficinaAutoServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        // Service container vazio Sprint 1 — feature-wish, sem clientes ativos.
    }
}
