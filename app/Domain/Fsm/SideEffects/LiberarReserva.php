<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Models\StockReservation;
use Illuminate\Database\Eloquent\Model;

/**
 * Marca reservas ACTIVE da transação como `released` (cancelamento OS).
 *
 * NÃO mexe em qty_available (reserva nunca foi consumida).
 * Idempotente: reservas terminais (consumed/released/expired) ignoradas.
 */
class LiberarReserva implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        StockReservation::query()
            ->where('business_id', $subject->business_id)
            ->where('transaction_id', $subject->getKey())
            ->where('status', StockReservation::STATUS_ACTIVE)
            ->update(['status' => StockReservation::STATUS_RELEASED]);
    }
}
