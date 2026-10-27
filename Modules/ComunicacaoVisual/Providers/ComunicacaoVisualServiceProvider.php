<?php

namespace Modules\ComunicacaoVisual\Providers;

use App\Events\RoteiroDaOsConcluido;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\ComunicacaoVisual\Console\Commands\ComvisHealthCommand;
use Modules\ComunicacaoVisual\Console\Commands\DemoSeedCommand;
use Modules\ComunicacaoVisual\Listeners\FechaOsPeloRoteiro;

/**
 * ServiceProvider Modules/ComunicacaoVisual (ADR 0121 §P7).
 *
 * Vertical gráfica rápida e comunicação visual BR (CNAE 1813). Em construção
 * — piloto previsto 2026-Q3 entre 6 saudáveis OfficeImpresso (Vargas/Extreme/
 * Gold/Zoom/Fixar/Mhundo/Produart).
 *
 * Sprint 1 scaffold: 8 peças RUNBOOK completas (InstallController + DataController
 * + lang PT-BR + config + RouteServiceProvider). Schema migrations entram Sprint 2+
 * conforme sinal qualificado [ADR 0105].
 *
 * @see memory/requisitos/Infra/RUNBOOK-criar-modulo.md
 *
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 */
class ComunicacaoVisualServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $this->registerConfig();
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'comunicacao-visual');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // A última etapa do roteiro no PCP fecha a OS da gráfica (flag COMVIS_FECHA_OS_PELO_ROTEIRO).
        Event::listen(RoteiroDaOsConcluido::class, [FechaOsPeloRoteiro::class, 'handle']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DemoSeedCommand::class,
                ComvisHealthCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('comunicacao-visual.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'comunicacao-visual');
    }
}
