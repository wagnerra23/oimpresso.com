<?php

namespace Modules\Financeiro\Observers;

use App\Transaction;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Jobs\CriarTituloDeVendaJob;

/**
 * Observer no Transaction core do UltimatePOS.
 *
 * Registrado no boot do FinanceiroServiceProvider:
 *   \App\Transaction::observe(\Modules\Financeiro\Observers\TransactionObserver::class);
 *
 * Disparos:
 *  - created (sell/purchase com payment_status=due) → cria título auto via job
 *  - updated (cancellation/payment changed) → recalcula valor_aberto
 *  - deleted → marca título como cancelado (não hard delete)
 *
 * Idempotência garantida pela UNIQUE em fin_titulos
 *   (business_id, origem, origem_id, parcela_numero) — TECH-0001.
 */
class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        // Só auto-cria título pra vendas/compras com pagamento devido
        if (! in_array($transaction->type, ['sell', 'purchase'], true)) {
            return;
        }

        if (! in_array($transaction->payment_status, ['due', 'partial'], true)) {
            return;
        }

        // Dispatch em queue 'financeiro' — não bloqueia salvamento da transaction
        CriarTituloDeVendaJob::dispatch($transaction->id)
            ->onQueue('financeiro');
    }

    public function updated(Transaction $transaction): void
    {
        // Só recalcula se status de pagamento OU final_total mudou
        if (! $transaction->wasChanged(['payment_status', 'final_total'])) {
            return;
        }

        // Recálculo é responsabilidade do listener de TransactionPaymentCreated/Updated.
        // Aqui apenas log pra observabilidade.
        Log::channel('financeiro')->info('Transaction atualizada', [
            'transaction_id' => $transaction->id,
            'payment_status' => $transaction->payment_status,
            'final_total' => $transaction->final_total,
        ]);
    }

    public function deleted(Transaction $transaction): void
    {
        // Cancelamento de venda → marca título como cancelado (não delete)
        \Modules\Financeiro\Models\Titulo::query()
            ->withoutGlobalScope(\Modules\Financeiro\Models\Concerns\BusinessScopeImpl::class)
            ->where('business_id', $transaction->business_id)
            ->where('origem', $transaction->type === 'sell' ? 'venda' : 'compra')
            ->where('origem_id', $transaction->id)
            ->where('status', '!=', 'quitado')
            ->update([
                'status' => 'cancelado',
                'observacoes' => trim((string) ($titulo->observacoes ?? '')) . "\n[Auto] Venda/compra cancelada em " . now()->toDateTimeString(),
            ]);
    }
}
