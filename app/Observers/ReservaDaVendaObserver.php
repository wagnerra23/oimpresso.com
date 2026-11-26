<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Fsm\Models\StockReservation;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encerra a reserva da venda quando a saída é efetivada (decisão [W] 2026-11-26).
 *
 * Regra do [W]: estoque reservado não é baixado de novo. Quando a saída é efetivada, a
 * quantidade sai do saldo reservado e baixa uma vez só do estoque total.
 *
 * - A baixa do estoque total é a do núcleo: a venda que vira `final` já baixa o saldo
 *   (`decreaseProductQuantity`). Este observer não mexe em `qty_available`.
 * - O que ele faz é tirar a quantidade do saldo reservado: as reservas ativas da venda deixam
 *   de ser ativas. Sem isso, a quantidade seguiria como "comprometido" (Necessidade de compra,
 *   lista de Produtos) até a reserva vencer, contada duas vezes.
 * - Usa `released`, não `consumed`: `consumed` significa "a baixa foi feita pela reserva", e o
 *   cancelamento da venda devolve ao saldo a reserva consumida (CancelamentoDaVenda). Marcar
 *   `consumed` aqui faria o cancelamento devolver duas vezes (a linha da venda e a reserva).
 * - Venda cancelada ou excluída antes de finalizar também libera a reserva.
 * - Reserva de insumo de OS (`pcp_ordem_id`) fica de fora: é baixada pelo apontamento da OS.
 *
 * Nunca propaga erro: falha aqui não pode travar a venda. A reserva que sobrar vence pelo
 * ExpireStaleReservationsJob.
 */
final class ReservaDaVendaObserver
{
    public function updated(Transaction $tx): void
    {
        if ($tx->type !== 'sell' || ! $tx->wasChanged('status')) {
            return;
        }
        if (in_array((string) $tx->getAttribute('status'), ['final', 'cancelled'], true)) {
            $this->encerrar($tx);
        }
    }

    public function deleted(Transaction $tx): void
    {
        if ($tx->type === 'sell') {
            $this->encerrar($tx);
        }
    }

    private function encerrar(Transaction $tx): void
    {
        try {
            if (! Schema::hasTable('stock_reservations')) {
                return;
            }
            $q = DB::table('stock_reservations')
                ->where('business_id', (int) $tx->business_id)
                ->where('transaction_id', (int) $tx->id)
                ->where('status', StockReservation::STATUS_ACTIVE);
            if (Schema::hasColumn('stock_reservations', 'pcp_ordem_id')) {
                $q->whereNull('pcp_ordem_id');
            }
            $q->update(['status' => StockReservation::STATUS_RELEASED, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
