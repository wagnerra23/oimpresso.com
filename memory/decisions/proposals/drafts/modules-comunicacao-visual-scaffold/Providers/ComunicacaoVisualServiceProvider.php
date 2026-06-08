<?php

/**
 * DRAFT — ServiceProvider Modules/ComunicacaoVisual.
 *
 * Imitar Modules/ADS/Providers/AdsServiceProvider.php (validado 2026-05-03).
 *
 * Responsabilidades:
 *  - register() → registra RouteServiceProvider e singletons (Services adicionados quando US-COMVIS-001+ implementadas).
 *  - boot()     → mergeConfig + loadMigrationsFrom + loadTranslationsFrom.
 *
 * IMPORTANTE (Felipe):
 *  - NUNCA registrar Listeners/Jobs aqui sem antes a US correspondente estar entregue.
 *  - Singletons de Services (OrcamentoCalculator, PosCalculoService, etc) entram em PRs separados conforme SPEC §6.1.
 *  - loadTranslationsFrom é OBRIGATORIO senao labels saem cruas em prod (RUNBOOK §7).
 */

namespace Modules\ComunicacaoVisual\Providers;

use Illuminate\Support\ServiceProvider;

class ComunicacaoVisualServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'comvis');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Felipe: singletons de Services entram aqui conforme US forem entregues.
        // Exemplo (placeholder — NAO implementar agora):
        // $this->app->singleton(\Modules\ComunicacaoVisual\Services\OrcamentoCalculator::class);
        // $this->app->singleton(\Modules\ComunicacaoVisual\Services\PosCalculoService::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('comvis.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'comvis');
    }
}
