<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Http\Requests\StoreServiceOrderItemRequest;
use Modules\OficinaAuto\Http\Requests\UpdateServiceOrderItemRequest;
use Modules\OficinaAuto\Services\ServiceOrderItemService;

/**
 * Wave 1.3 US-OFICINA-027 — HTTP layer pra `oficina_service_order_items`.
 *
 * Domain (Model + Service + 10 Pest) entregue em Wave 27 G1 (2026-05-17) + Wave 28 polish.
 * Esta Controller só wirea HTTP CRUD pra UI consumir — drawer Cowork seção "PEÇAS &
 * MÃO DE OBRA" (Wave 2) chama estes endpoints.
 *
 * Multi-tenant Tier 0 [ADR 0093]: business_id derivado de `auth()->user()` (NUNCA
 * request). Service `addItem` faz double-check cross-tenant (OS pertence ao biz)
 * via `withoutGlobalScopes()->where('business_id', ...)`.
 *
 * Rotas registradas em `Modules/OficinaAuto/Routes/web.php`:
 *   POST   /oficina-auto/ordens-servico/{order}/items         → store
 *   PUT    /oficina-auto/ordens-servico/{order}/items/{item}  → update
 *   DELETE /oficina-auto/ordens-servico/{order}/items/{item}  → destroy
 *
 * Throttle 60/1 nas mutações (padrão módulo, ServiceOrderController).
 *
 * @see Modules\OficinaAuto\Services\ServiceOrderItemService
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-027
 */
class ServiceOrderItemController extends Controller
{
    public function __construct(private readonly ServiceOrderItemService $service) {}

    /**
     * Cria item na OS (peça / mão-de-obra / serviço terceiro).
     *
     * Multi-tenant: Service::addItem rejeita cross-tenant via `InvalidArgumentException`
     * "OS não pertence ao business" — convertido em HTTP 422 aqui.
     */
    public function store(StoreServiceOrderItemRequest $request, ServiceOrder $order): JsonResponse
    {
        // Policy sameTenant — defesa em profundidade (Service ainda rechecará).
        $this->authorize('update', $order);

        $businessId = (int) ($request->user()->business_id ?? 0);

        try {
            $item = $this->service->addItem($businessId, $order->id, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id'             => $item->id,
            'tipo'           => $item->tipo,
            'descricao'      => $item->descricao,
            'quantidade'     => (float) $item->quantidade,
            'valor_unitario' => (float) $item->valor_unitario,
            'valor_total'    => (float) $item->valor_total,
            'product_id'     => $item->product_id,
            'notes'          => $item->notes,
            'created_at'     => $item->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Atualiza item existente. Policy via parent OS — operador da OS edita os items dela.
     * Auto-recalc `valor_total` acontece no Model `updating` hook (ServiceOrderItem.php).
     */
    public function update(
        UpdateServiceOrderItemRequest $request,
        ServiceOrder $order,
        ServiceOrderItem $item,
    ): JsonResponse {
        $this->authorize('update', $order);

        // Tier 0 hardening: garante que o item pertence à OS da URL (não só ao biz).
        // Sem isso, biz=164 poderia editar item da OS #1 passando como /ordens-servico/2/items/1.
        abort_unless($item->service_order_id === $order->id, 404);

        $item->fill($request->validated())->save();

        return response()->json([
            'id'             => $item->id,
            'tipo'           => $item->tipo,
            'descricao'      => $item->descricao,
            'quantidade'     => (float) $item->quantidade,
            'valor_unitario' => (float) $item->valor_unitario,
            'valor_total'    => (float) $item->valor_total,
            'product_id'     => $item->product_id,
            'notes'          => $item->notes,
            'updated_at'     => $item->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Remove item (soft delete — preserva audit append-only LGPD D7.b).
     */
    public function destroy(Request $request, ServiceOrder $order, ServiceOrderItem $item): JsonResponse
    {
        $this->authorize('update', $order);

        abort_unless($item->service_order_id === $order->id, 404);

        $item->delete();

        return response()->json(['deleted' => true, 'id' => $item->id]);
    }
}
