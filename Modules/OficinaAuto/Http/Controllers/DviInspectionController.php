<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Http\Requests\StoreDviRequest;
use Modules\OficinaAuto\Http\Requests\UpdateDviRequest;
use Modules\OficinaAuto\Services\DviInspectionService;

/**
 * DviInspectionController — CRUD HTTP de itens DVI (Vistoria Digital).
 *
 * Wave 3 OficinaAuto US-OFICINA-035. JSON-only — UI vai consumir via fetch
 * direto do drawer ServiceOrderRichSheet (Wave 3b futura, depende PR #1624).
 *
 * Endpoints (Routes/web.php):
 *   POST   /oficina-auto/ordens-servico/{order}/dvi          → store (201)
 *   PUT    /oficina-auto/ordens-servico/{order}/dvi/{item}   → update (200)
 *   DELETE /oficina-auto/ordens-servico/{order}/dvi/{item}   → destroy (204)
 *
 * Defesa em profundidade:
 *  1. Policy `update(ServiceOrder)` — Spatie permission + sameTenant guard ADR 0093
 *  2. abort_unless cross-OS — item DEVE pertencer à OS da rota (proteção contra
 *     /orders/1/dvi/{item_da_OS_2} mesmo dentro do mesmo business)
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-035
 */
class DviInspectionController extends Controller
{
    public function __construct(private DviInspectionService $service)
    {
    }

    /**
     * Cria novo item DVI numa OS. 201 + payload do item criado.
     */
    public function store(StoreDviRequest $request, ServiceOrder $order): JsonResponse
    {
        // Policy: só quem pode editar a OS pode adicionar itens DVI (sameTenant guard).
        $this->authorize('update', $order);

        $item = $this->service->addItem(
            (int) $order->business_id,
            (int) $order->id,
            $request->validated()
        );

        return response()->json([
            'item' => $this->shapeItem($item),
        ], 201);
    }

    /**
     * Atualiza item DVI existente. 200 + payload atualizado.
     */
    public function update(UpdateDviRequest $request, ServiceOrder $order, OaInspectionItem $item): JsonResponse
    {
        $this->authorize('update', $order);

        // Cross-OS guard: item PRECISA pertencer à OS da rota
        abort_unless((int) $item->service_order_id === (int) $order->id, 404);

        $item->fill($request->validated())->save();

        return response()->json([
            'item' => $this->shapeItem($item->fresh()),
        ]);
    }

    /**
     * Remove item DVI (soft delete). 204 sem body.
     */
    public function destroy(ServiceOrder $order, OaInspectionItem $item): JsonResponse
    {
        $this->authorize('update', $order);

        abort_unless((int) $item->service_order_id === (int) $order->id, 404);

        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * Shape canônico de OaInspectionItem pra JSON API (espelha drawer V0).
     *
     * @return array{id:int, categoria:string, descricao:string, severity:string, recomendacao:string|null, valor_recomendado:float|null, metadata:array|null, photo_url:string|null, sort_order:int}
     */
    private function shapeItem(OaInspectionItem $item): array
    {
        return [
            'id'                => (int) $item->id,
            'categoria'         => (string) $item->categoria,
            'descricao'         => (string) $item->descricao,
            'severity'          => (string) $item->severity,
            'recomendacao'      => $item->recomendacao,
            'valor_recomendado' => $item->valor_recomendado !== null ? (float) $item->valor_recomendado : null,
            'metadata'          => $item->metadata,
            'photo_url'         => $item->photo_url,
            'sort_order'        => (int) $item->sort_order,
        ];
    }
}
