<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Models\StockReservation;
use App\VariationLocationDetails;
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
        // INV-1 (DOC-RAIZ-ESTOQUE §7): a baixa precisa ser auditável. Quando a tabela
        // activity_log existe (prod), gravamos via modelo Eloquent pra disparar o
        // LogsActivity 'inventory.stock' do VariationLocationDetails — mesma trilha do
        // ProductUtil. Em ambientes sem activity_log (ex: teste FSM sqlite com schema
        // mínimo) caímos no DB::table, preservando comportamento legado.
        $hasActivityLog = Schema::hasTable('activity_log');
        $hasProducts = Schema::hasTable('products');

        foreach ($reservations as $r) {
            $r->update(['status' => StockReservation::STATUS_CONSUMED]);

            if (! $hasVld) {
                continue;
            }

            // INV-5: produto sem controle de estoque não movimenta saldo (checado só
            // quando o catálogo está disponível — não bloqueia envs de teste sem products).
            if ($hasProducts && $r->product_id) {
                $enableStock = DB::table('products')->where('id', $r->product_id)->value('enable_stock');
                if ($enableStock !== null && (int) $enableStock !== 1) {
                    continue;
                }
            }

            if ($hasActivityLog) {
                $vld = VariationLocationDetails::where('variation_id', $r->variation_id)
                    ->where('location_id', $r->location_id)
                    ->first();

                if (! $vld) {
                    continue;
                }

                // Clamp em 0 preservado (DOC-RAIZ-ESTOQUE §5 reserva→consumo).
                $vld->qty_available = max(0, (float) $vld->qty_available - (float) $r->qty_reserved);
                $vld->save(); // dispara LogsActivity 'inventory.stock'

                continue;
            }

            // Fallback sem audit (ambiente de teste/legado sem activity_log).
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
