<?php

namespace Modules\Producao\Providers;

use App\Domain\Fsm\Models\SaleStageHistory;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\Producao\Console\FecharTurnoCommand;
use Modules\Producao\Jobs\CriarOsDaVendaJob;
use Modules\Producao\Services\CriarOsDaVenda;
use Modules\Producao\Services\OsDaVenda;
use Modules\Producao\Services\SituacaoDaOrdem;

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

        // US-PCP-025 (UC-FLX-28): pagamento gravado na venda — baixa manual, PDV ou retorno do banco que
        // cria o pagamento — tenta liberar as OS dessa venda pelo sinal. Usa a empresa do PRÓPRIO
        // pagamento (Tier 0). Falha aqui nunca derruba o pagamento: só fica no log.
        TransactionPayment::saved(function (TransactionPayment $tp) {
            if ($tp->transaction_id === null || $tp->business_id === null || (int) $tp->is_return === 1) {
                return;
            }
            try {
                app(SituacaoDaOrdem::class)->liberarPeloSinalDaVenda((int) $tp->business_id, (int) $tp->transaction_id);
            } catch (\Throwable $e) {
                Log::warning('producao.sinal: falha ao liberar OS pelo pagamento', ['business_id' => $tp->business_id, 'transaction_id' => $tp->transaction_id, 'erro' => $e->getMessage()]);
            }
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // UC-FLX-33 · US-COMVIS-019: o orçamento da CV guarda o custo da hora de máquina do dia.
        $this->app->bind(\App\Contracts\Producao\CustoHoraDoPosto::class, \Modules\Producao\Services\CustoHoraDoPostoService::class);
        // US-FIN-070 (custeio RKW): o Financeiro lê horas e troca custo-hora só por aqui.
        $this->app->bind(\App\Contracts\Producao\HorasDosPostos::class, \Modules\Producao\Services\HorasDosPostosService::class);
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
