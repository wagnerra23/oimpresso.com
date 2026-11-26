<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Models\StockReservation;
use App\Domain\Inventory\Services\BomResolver;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Scopes\ScopeByBusiness;

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
 *
 * V2 (US-INV-003): pra cada item do payload, chama BomResolver. Se o produto
 * é kit (product_bom) OU combo legacy, cria 1 stock_reservation POR COMPONENTE-FOLHA
 * em vez de 1 reserva pro produto pai. Comportamento legacy preservado pra
 * produtos simples (1 row resolvida → 1 reserva).
 *
 * Idempotente: skip linha se já existe reserva active da mesma transaction
 * com mesmo (product_id, variation_id, location_id) — evita duplicação em
 * re-execução de FSM action.
 */
class ReservarEstoque implements SideEffectInterface
{
    public function __construct(private ?BomResolver $bomResolver = null)
    {
        // Permite injeção (Container) E uso direto (new ReservarEstoque)
        // pra compat com chamadas existentes em FSM ExecuteStageActionService.
        $this->bomResolver ??= app(BomResolver::class);
    }

    public function execute(Model $subject, array $payload = []): void
    {
        $items = $payload['items'] ?? [];
        if ($items === []) {
            $items = $this->itensDaVenda($subject);
        }
        $expiresInDays = (int) ($payload['expires_in_days'] ?? 30);
        $expiresAt = Carbon::now()->addDays($expiresInDays);
        $businessId = (int) $subject->business_id;
        $transactionId = $subject->getKey();

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variationId = (int) $item['variation_id'];
            $locationId = (int) $item['location_id'];
            $qty = (float) $item['qty'];

            // Resolve BOM — produto simples retorna 1 row (ele mesmo);
            // kit retorna N rows (componentes-folha).
            $resolved = $this->bomResolver->resolve(
                businessId: $businessId,
                productId: $productId,
                variationId: $variationId,
                qtyParent: $qty
            );

            foreach ($resolved as $componentRow) {
                $compProductId = (int) $componentRow['product_id'];
                $compVariationId = (int) ($componentRow['variation_id'] ?? 0);
                $compQty = (float) $componentRow['qty'];

                // Idempotência: pula se já reservou esse componente nesta transaction.
                // Use withoutGlobalScope + business_id explícito porque side-effect
                // pode rodar em job/CLI sem session (ADR 0093 §Job assíncrono).
                $exists = StockReservation::withoutGlobalScope(ScopeByBusiness::class)
                    ->where('business_id', $businessId)
                    ->where('transaction_id', $transactionId)
                    ->where('product_id', $compProductId)
                    ->where('variation_id', $compVariationId)
                    ->where('location_id', $locationId)
                    ->where('status', StockReservation::STATUS_ACTIVE)
                    ->exists();

                if ($exists) {
                    continue;
                }

                StockReservation::create([
                    'business_id' => $businessId,
                    'transaction_id' => $transactionId,
                    'product_id' => $compProductId,
                    'variation_id' => $compVariationId,
                    'location_id' => $locationId,
                    'qty_reserved' => $compQty,
                    'status' => StockReservation::STATUS_ACTIVE,
                    'expires_at' => $expiresAt,
                ]);
            }
        }
    }

    /**
     * Sem `payload.items`, a reserva usa as linhas da própria venda (decisão [W] 2026-11-26:
     * a reserva acontece sem mexer na tela de Venda, que não manda a lista).
     *
     * - Só venda (`type = sell`) ainda não finalizada. A venda finalizada já baixou o estoque
     *   pelo núcleo; reservar depois dela contaria a mesma quantidade duas vezes.
     * - Só linha raiz (`parent_sell_line_id` nulo): os componentes de kit/combo vêm do
     *   BomResolver, e modificador não é estoque.
     * - Só produto que controla estoque, e só o que ainda está vendido (quantidade − devolvida).
     * - O local é o da venda.
     *
     * O encerramento da reserva quando a saída é efetivada fica no ReservaDaVendaObserver.
     *
     * @return list<array{product_id:int, variation_id:int, location_id:int, qty:float}>
     */
    private function itensDaVenda(Model $subject): array
    {
        if (! $subject instanceof Transaction || $subject->type !== 'sell') {
            return [];
        }
        if ((string) $subject->getAttribute('status') === 'final' || empty($subject->location_id)) {
            return [];
        }

        $linhas = DB::table('transaction_sell_lines as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.transaction_id', $subject->getKey())
            ->where('p.business_id', $subject->business_id)
            ->whereNull('l.parent_sell_line_id')
            ->where('p.enable_stock', 1)
            ->get(['l.product_id', 'l.variation_id', 'l.quantity', 'l.quantity_returned']);

        $itens = [];
        foreach ($linhas as $l) {
            $qty = (float) $l->quantity - (float) ($l->quantity_returned ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $itens[] = [
                'product_id' => (int) $l->product_id,
                'variation_id' => (int) $l->variation_id,
                'location_id' => (int) $subject->location_id,
                'qty' => $qty,
            ];
        }

        return $itens;
    }
}
