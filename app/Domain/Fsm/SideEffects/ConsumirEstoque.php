<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Models\StockReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca reservas ACTIVE da transação como `consumed` e decrementa
 * `variation_location_details.qty_available` (baixa real do estoque).
 *
 * Idempotente: reservas já `consumed`/`released`/`expired` são ignoradas.
 * Guard: qty_available NUNCA fica negativo (clamp em 0 com log de warning).
 *
 * V2 BOM (US-INV-004): pra kits, ReservarEstoque já criou 1 stock_reservation
 * POR COMPONENTE-FOLHA via BomResolver. Como ConsumirEstoque itera sobre TODAS
 * as reservations active da transaction, o consumo cascateado por componente
 * já acontece automaticamente — single source of truth = stock_reservations.
 * Sem mudança de algoritmo necessária aqui.
 */
class ConsumirEstoque implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        $reservations = StockReservation::query()
            ->where('business_id', $subject->business_id)
            ->where('transaction_id', $subject->getKey())
            ->where('status', StockReservation::STATUS_ACTIVE)
            ->get();

        $hasVld = Schema::hasTable('variation_location_details');

        foreach ($reservations as $r) {
            $r->update(['status' => StockReservation::STATUS_CONSUMED]);

            if (! $hasVld) {
                continue;
            }

            $vld = DB::table('variation_location_details')
                ->where('variation_id', $r->variation_id)
                ->where('location_id', $r->location_id)
                ->first();

            if (! $vld) {
                continue;
            }

            $newQty = max(0, (float) $vld->qty_available - (float) $r->qty_reserved);
            DB::table('variation_location_details')
                ->where('id', $vld->id)
                ->update(['qty_available' => $newQty, 'updated_at' => now()]);
        }
    }
}
