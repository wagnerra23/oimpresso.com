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
 * onda 2 se latencia em /sells/create incomodar.
 *
 * Idempotencia garantida pela UNIQUE em fin_titulos
 *   (business_id, origem, origem_id, parcela_numero) — TECH-0001.
 */
class TransactionObserver
{
    public function __construct(private TituloAutoService $service)
    {
    }

    public function created(Transaction $tx): void
    {
        $this->service->sincronizarDeVenda($tx);
    }

    public function updated(Transaction $tx): void
    {
        // So re-sincroniza se algo financeiramente relevante mudou.
        if (! $tx->wasChanged(['payment_status', 'final_total', 'pay_term_number', 'pay_term_type'])) {
            return;
        }

        $this->service->sincronizarDeVenda($tx);
    }

    public function deleted(Transaction $tx): void
    {
        $this->service->cancelarSeExistir($tx, motivo: 'venda excluida');
    }
}
