<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Models\StockReservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Cria stock_reservations active SEM mexer em variation_location_details.qty_available.
 *
 * Payload esperado:
 *   [
 *     'items' => [
 *        ['product_id' => N, 'variation_id' => N, 'location_id' => N, 'qty' => float],
 *        ...
 *     ],
 *     'expires_in_days' => 30  // opcional, default 30
 *   ]
 *
 * Multi-tenant Tier 0 (ADR 0093) — usa $subject->business_id (NUNCA session).
 */
class ReservarEstoque implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        $items = $payload['items'] ?? [];
        $expiresInDays = (int) ($payload['expires_in_days'] ?? 30);
        $expiresAt = Carbon::now()->addDays($expiresInDays);

        foreach ($items as $item) {
            StockReservation::create([
                'business_id' => $subject->business_id,
                'transaction_id' => $subject->getKey(),
                'product_id' => (int) $item['product_id'],
                'variation_id' => (int) $item['variation_id'],
                'location_id' => (int) $item['location_id'],
                'qty_reserved' => (float) $item['qty'],
                'status' => StockReservation::STATUS_ACTIVE,
                'expires_at' => $expiresAt,
            ]);
        }
    }
}
