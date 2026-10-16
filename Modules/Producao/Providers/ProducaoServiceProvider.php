<?php

namespace Modules\Producao\Providers;

use App\Transaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Producao\Console\FecharTurnoCommand;
use Modules\Producao\Services\PedidoEmEsperaService;

class ProducaoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Thread 04 (UC-PCV-22/36): fecha o cronômetro esquecido. A cada 15 min, sem sobreposição;
        // o comando só age depois do fim do turno de cada empresa e é idempotente.
        $this->commands([FecharTurnoCommand::class]);

        // Decisão [W] 2026-10-16: venda em espera pausa as etapas dela no PCP. Escuta a mudança de
        // etapa (origem → destino), não a chave da ação, para valer para qualquer ação.
        Event::listen('eloquent.updated: '.Transaction::class, fn (Transaction $t) => app(PedidoEmEsperaService::class)->aoMudarEtapa($t));
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('producao:fechar-turno')->everyFifteenMinutes()->withoutOverlapping();
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('producao.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'producao');
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'producao');
    }
}
