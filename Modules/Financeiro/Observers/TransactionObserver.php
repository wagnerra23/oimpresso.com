<?php

namespace Modules\Financeiro\Observers;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Services\DevolucaoVendaTituloService;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Observer no Transaction core do UltimatePOS.
 *
 * Registrado no boot do FinanceiroServiceProvider via:
 *   \App\Transaction::observe(\Modules\Financeiro\Observers\TransactionObserver::class);
 *
 * MVP: sincronização sincrona (sem queue). Job assincrono fica pra
 * onda futura se latencia em /sells/create incomodar.
 *
 * Idempotencia garantida pela UNIQUE em fin_titulos
 *   (business_id, origem, origem_id, parcela_numero) — TECH-0001.
 *
 * Onda 2 (2026-04-25): cobre type='sell' E type='purchase' via
 * sincronizarDeTransacao() (Service unificado).
 *
 * 2026-11-03: type='sell_return' abate o título da venda devolvida
 * (DevolucaoVendaTituloService — decisão [W] "Abater o título aberto").
 *
 * US-FIN-059 (2026-11-05): o Financeiro é módulo opcional e não derruba a venda. Cada
 * reação roda em `semDerrubarATransacao()`: dentro de um `DB::transaction` próprio (que
 * vira savepoint quando o core já abriu transação, como no SellPosController) e num
 * try/catch que chama `report()` e segue. Se o espelho falha no meio, o savepoint desfaz
 * o que ele já tinha gravado — não sobra título pela metade — e a venda é gravada.
 * O título que faltou aparece no health-check da bridge (US-FIN-040) e no resync.
 */
class TransactionObserver
{
    public function __construct(private TituloAutoService $service)
    {
    }

    public function created(Transaction $tx): void
    {
        $this->semDerrubarATransacao($tx, 'created', function () use ($tx): void {
            if (DevolucaoVendaTituloService::ehDevolucao($tx)) {
                app(DevolucaoVendaTituloService::class)->sincronizar($tx);

                return;
            }

            $this->service->sincronizarDeTransacao($tx);
        });
    }

    public function updated(Transaction $tx): void
    {
        // So re-sincroniza se algo financeiramente relevante mudou. `sub_type` entra pela
        // bonificação (P-154): a venda que vira bonificação perde o título.
        if (! $tx->wasChanged(['payment_status', 'final_total', 'pay_term_number', 'pay_term_type', 'due_date', 'sub_type'])) {
            return;
        }

        $this->semDerrubarATransacao($tx, 'updated', function () use ($tx): void {
            if (DevolucaoVendaTituloService::ehDevolucao($tx)) {
                app(DevolucaoVendaTituloService::class)->sincronizar($tx);

                return;
            }

            $this->service->sincronizarDeTransacao($tx);
        });
    }

    public function deleted(Transaction $tx): void
    {
        $this->semDerrubarATransacao($tx, 'deleted', function () use ($tx): void {
            if (DevolucaoVendaTituloService::ehDevolucao($tx)) {
                app(DevolucaoVendaTituloService::class)->estornarSeExistir($tx);

                return;
            }

            $motivo = match ($tx->type) {
                'sell' => 'venda excluida',
                'purchase' => 'compra excluida',
                default => 'transação excluída',
            };

            $this->service->cancelarSeExistir($tx, motivo: $motivo);
        });
    }

    /**
     * Roda a reação do Financeiro sem deixar a exceção chegar ao `save()` do core.
     */
    private function semDerrubarATransacao(Transaction $tx, string $evento, callable $trabalho): void
    {
        try {
            DB::transaction(static function () use ($trabalho): void {
                $trabalho();
            });
        } catch (\Throwable $e) {
            Log::warning('financeiro.observer.transaction.falhou', [
                'business_id' => $tx->business_id,
                'transaction_id' => $tx->id,
                'type' => $tx->type,
                'evento' => $evento,
                'erro' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
