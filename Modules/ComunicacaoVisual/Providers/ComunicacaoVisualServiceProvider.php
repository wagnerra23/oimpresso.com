<?php

namespace Modules\ComunicacaoVisual\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider Modules/ComunicacaoVisual (ADR 0121 §P7).
 *
 * Vertical gráfica rápida e comunicação visual BR (CNAE 1813). Em construção
 * — piloto previsto 2026-Q3 entre 6 saudáveis OfficeImpresso (Vargas/Extreme/
 * Gold/Zoom/Fixar/Mhundo/Produart).
 *
 * Sprint 1 scaffold: módulo nWidart vazio + RepairSettingsSeeder com vocabulário
 * gráfico (machine/Plotter/ACM/Lona). Code real entra Sprint 2+ conforme
 * sinal qualificado [ADR 0105].
 *
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 */
class ComunicacaoVisualServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        // Service container vazio Sprint 1.
    }
}
