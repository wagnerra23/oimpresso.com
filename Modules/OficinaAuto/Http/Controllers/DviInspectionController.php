<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Http\Requests\StoreDviRequest;
use Modules\OficinaAuto\Http\Requests\UpdateDviRequest;
use Modules\OficinaAuto\Http\Requests\UploadDviPhotoRequest;
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
    public function __construct(
        private DviInspectionService $service,
        private ArquivosService $arquivos,
    ) {
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
     * Gap 1 (2026-05-26) — upload foto pra item DVI via Modules/Arquivos backbone.
     *
     * Pattern AutoVitals/Tekmetric 2026: foto inline em CADA item DVI (não anexo
     * solto da OS). Caminhão basculante Martinho biz=164 — motorista leva foto
     * antes/depois pra ressarcir transportadora 3a (sub-vertical 4 ADR 0194).
     *
     * Multi-tenant Tier 0 ([ADR 0093]):
     *  1. Policy `update(ServiceOrder)` — Spatie + sameTenant guard
     *  2. abort_unless cross-OS — item DEVE pertencer à OS da rota
     *  3. ArquivosService::attach lê business_id da sessão e propaga global scope
     *
     * 201 Created + `{arquivo: {id, original_name, mime_type, size_bytes,
     * display_url}}` pra UI atualizar grid sem refetch da OS inteira.
     */
    public function uploadPhoto(UploadDviPhotoRequest $request, ServiceOrder $order, OaInspectionItem $item): JsonResponse
    {
        $this->authorize('update', $order);

        // Cross-OS guard: item PRECISA pertencer à OS da rota (defesa em profundidade
        // mesmo dentro do mesmo business — espelha pattern update/destroy acima).
        abort_unless((int) $item->service_order_id === (int) $order->id, 404);

        $arquivo = $this->arquivos->attach(
            $item,
            $request->file('photo'),
            ['context' => 'oficina-auto-dvi-photo'],
        );

        return response()->json([
            'arquivo' => $this->shapeArquivo($arquivo),
        ], 201);
    }

    /**
     * Gap 1 (2026-05-26) — soft-delete foto de item DVI.
     *
     * Defesa em profundidade:
     *  1. Policy `update(ServiceOrder)`
     *  2. Cross-OS guard — item pertence à OS da rota
     *  3. Cross-item guard — arquivo `arquivable` PRECISA ser este item (não outro)
     *  4. Global scope Arquivo (business_id) já garante cross-tenant
     *
     * ArquivosService::softDelete grava audit-log + emite OTel span.
     */
    public function deletePhoto(ServiceOrder $order, OaInspectionItem $item, Arquivo $arquivo): JsonResponse
    {
        $this->authorize('update', $order);

        abort_unless((int) $item->service_order_id === (int) $order->id, 404);

        // Cross-item guard: arquivo morphTo PRECISA ser este OaInspectionItem.
        abort_unless(
            $arquivo->arquivable_type === OaInspectionItem::class
            && (int) $arquivo->arquivable_id === (int) $item->id,
            404
        );

        $this->arquivos->softDelete($arquivo);

        return response()->json(null, 204);
    }

    /**
     * Shape canônico de Arquivo pra JSON API (espelha frontend DviPhotoGrid).
     *
     * NÃO expõe storage_path/MD5/business_id (defesa em profundidade — display_url
     * é signed quando bucket=sensitive, asset() direto caso contrário).
     *
     * @return array{id:int, original_name:string, mime_type:string, size_bytes:int, display_url:string, created_at:string|null}
     */
    private function shapeArquivo(Arquivo $arquivo): array
    {
        return [
            'id'             => (int) $arquivo->id,
            'original_name'  => (string) ($arquivo->original_name ?? ''),
            'mime_type'      => (string) $arquivo->mime_type,
            'size_bytes'     => (int) $arquivo->size_bytes,
            'display_url'    => (string) $arquivo->display_url,
            'created_at'     => $arquivo->created_at?->toIso8601String(),
        ];
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
