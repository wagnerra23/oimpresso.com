<?php

namespace Modules\Producao\Providers;

use App\Domain\Fsm\Models\SaleStageHistory;
use App\Transaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Modules\Producao\Console\FecharTurnoCommand;
use Modules\Producao\Jobs\CriarOsDaVendaJob;
use Modules\Producao\Services\CriarOsDaVenda;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Services\OsDaVenda;
use Modules\Producao\Services\ReservaDeInsumoDaOs;

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
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('producao:fechar-turno')->everyFifteenMinutes()->withoutOverlapping();
        });

        // ADR 0436 (UC-FLX-01): a venda de um modelo entrou em "Aprovado" → uma OS por item, depois
        // do commit da transição. O motor grava o histórico em toda transição, de venda ou de OS.
        SaleStageHistory::created(function (SaleStageHistory $h) {
            $biz = (int) $h->business_id;
            if (app(CriarOsDaVenda::class)->deveCriar($biz, $h->to_stage_id !== null ? (int) $h->to_stage_id : null)) {
                CriarOsDaVendaJob::dispatch($biz, (int) $h->transaction_id, $h->user_id !== null ? (int) $h->user_id : null)->afterCommit();
            }
        });

        // ADR 0436: "Concluir" da venda (→ Entregue) exige as OS prontas. Roda no updating da venda,
        // dentro da transação do motor: a recusa desfaz a transição inteira.
        Transaction::updating(function (Transaction $tx) {
            if ($tx->isDirty('current_stage_id')) {
                app(OsDaVenda::class)->guardar((int) $tx->business_id, (int) $tx->id, $tx->current_stage_id !== null ? (int) $tx->current_stage_id : null);
            }
        });

        // US-PCP-031 · UC-FLX-14: a reserva do insumo da OS sob encomenda acaba quando a OS acaba (o que
        // sobrou é liberado) ou quando a venda é cancelada. Dentro da transação do motor, no `updated`.
        Ordem::updated(function (Ordem $o) {
            if ($o->wasChanged('current_stage_id') && $o->current_stage_id !== null
                && in_array(DB::table('sale_process_stages')->where('id', $o->current_stage_id)->value('key'), ['concluida', 'cancelada'], true)) {
                app(ReservaDeInsumoDaOs::class)->liberar((int) $o->business_id, (int) $o->id);
            }
        });
        Transaction::updated(function (Transaction $tx) {
            if ($tx->type === 'sell' && $tx->wasChanged('current_stage_id') && $tx->current_stage_id !== null
                && (bool) DB::table('sale_process_stages')->where('id', $tx->current_stage_id)->value('is_cancelled')) {
                app(ReservaDeInsumoDaOs::class)->liberarDaVenda((int) $tx->business_id, (int) $tx->id);
            }
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // UC-FLX-33 · US-COMVIS-019: o orçamento da CV guarda o custo da hora de máquina do dia.
        $this->app->bind(\App\Contracts\Producao\CustoHoraDoPosto::class, \Modules\Producao\Services\CustoHoraDoPostoService::class);
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
