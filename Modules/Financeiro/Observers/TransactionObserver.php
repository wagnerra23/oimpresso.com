<?php

namespace Modules\Financeiro\Observers;

use App\Transaction;
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
 */
class TransactionObserver
{
    public function __construct(private TituloAutoService $service)
    {
    }

    public function created(Transaction $tx): void
    {
        $this->service->sincronizarDeTransacao($tx);
    }

    public function updated(Transaction $tx): void
    {
        // So re-sincroniza se algo financeiramente relevante mudou.
        if (! $tx->wasChanged(['payment_status', 'final_total', 'pay_term_number', 'pay_term_type', 'due_date'])) {
            return;
        }

        $this->service->sincronizarDeTransacao($tx);
    }

    public function deleted(Transaction $tx): void
    {
        $motivo = match ($tx->type) {
            'sell' => 'venda excluida',
            'purchase' => 'compra excluida',
            default => 'transação excluída',
        };

        $this->service->cancelarSeExistir($tx, motivo: $motivo);
    }
}
