<?php

namespace Modules\Fisio\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Fisio\Console\LembretesCommand;
use Modules\Fisio\Contracts\LinkDoPortal;
use Modules\Fisio\Events\CheckinRegistrado;
use Modules\Fisio\Listeners\LancarXpDoCheckin;
use Modules\Fisio\Services\Whatsapp\LinkDoPortalPorToken;

class FisioServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerTranslations();
        // Views só para documento gerado no servidor (PDF do prontuário, UC-FPS-19). Telas são Inertia.
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'fisio');
        // US-FISIO-003: as 11 tabelas fisio_* (ADR 0437). Tabelas novas: sem efeito em empresa sem o pacote.
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        // US-FISIO-008: ponto de entrada único do jogo — check-in gravado lança o XP no ledger.
        Event::listen(CheckinRegistrado::class, [LancarXpDoCheckin::class, 'handle']);

        // US-FISIO-009: lembrete diário por WhatsApp (pelo módulo Whatsapp). Um job por empresa com o pacote;
        // o job é idempotente (1 lembrete por paciente por dia) e respeita o horário de silêncio.
        $this->commands([LembretesCommand::class]);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('fisio:lembretes')->dailyAt((string) config('fisio.whatsapp.lembrete_horario', '08:30'))->withoutOverlapping();
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        // Ponto de integração com o portal do paciente (US-FISIO-007a): emite token + PIN e devolve a URL.
        $this->app->bind(LinkDoPortal::class, LinkDoPortalPorToken::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('fisio.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'fisio');
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'fisio');
    }
}