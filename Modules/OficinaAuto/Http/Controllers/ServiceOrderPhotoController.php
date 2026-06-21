<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Http\Requests\StoreServiceOrderPhotoRequest;
use Modules\OficinaAuto\Http\Requests\UpdateServiceOrderPhotoLabelRequest;

/**
 * ServiceOrderPhotoController — CRUD HTTP das fotos do laudo OS-level (Fotos & Laudo).
 *
 * F3 OS-V2-1 (fila TELAS_REVIEW_QUEUE · 2026-06-09). Porta pro drawer real o
 * protótipo Cowork aprovado [W] 2026-06-09: zona de upload (vazio/enviando/preenchido)
 * + lightbox com legenda editável. As fotos entram no laudo impresso A4
 * ("Fotos da vistoria").
 *
 * Distinto do DviInspectionController::uploadPhoto (foto POR item DVI · morphTo
 * OaInspectionItem): aqui o anexo é da própria ServiceOrder (morphTo ServiceOrder
 * via trait HasArquivos). $order->arquivos() == fotos do laudo da OS (nada mais
 * anexa direto na OS hoje).
 *
 * Endpoints (Routes/web.php):
 *   GET    /oficina-auto/ordens-servico/{order}/fotos              → index (200)
 *   POST   /oficina-auto/ordens-servico/{order}/fotos              → store (201)
 *   PATCH  /oficina-auto/ordens-servico/{order}/fotos/{arquivo}    → updateLabel (200)
 *   DELETE /oficina-auto/ordens-servico/{order}/fotos/{arquivo}    → destroy (204)
 *
 * Defesa em profundidade (multi-tenant Tier 0 · [ADR 0093]):
 *  1. Policy view/update(ServiceOrder) — Spatie permission + sameTenant guard
 *  2. Global scope Arquivo (business_id) — cross-tenant fechado no Model
 *  3. Cross-owner guard — arquivo `arquivable` PRECISA ser esta ServiceOrder
 *
 * @see Modules/OficinaAuto/Entities/ServiceOrder.php (use HasArquivos)
 * @see memory/sessions/2026-06-09-avaliacao-os-sweep-fila-v2.md
 */
class ServiceOrderPhotoController extends Controller
{
    public function __construct(
        private ArquivosService $arquivos,
    ) {
    }

    /**
     * Lista as fotos do laudo da OS (ordem cronológica de upload).
     */
    public function index(ServiceOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

        // map() com closure SEM tipo no param + @var: a relação HasArquivos é
        // MorphMany sem generic (Larastan tipa a coleção como Model, não Arquivo —
        // dívida US-ARQ-TYPE no trait). O @var estreita pra Arquivo sem mexer no trait.
        $fotos = $order->arquivos()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function ($a) {
                /** @var Arquivo $a */
                return $this->shape($a);
            })
            ->all();

        return response()->json(['fotos' => $fotos]);
    }

    /**
     * Anexa foto à OS (laudo geral). 201 + payload do arquivo criado.
     */
    public function store(StoreServiceOrderPhotoRequest $request, ServiceOrder $order): JsonResponse
    {
        $this->authorize('update', $order);

        $arquivo = $this->arquivos->attach(
            $order,
            $request->file('photo'),
            ['context' => 'oficina-auto-laudo-photo'],
        );

        return response()->json(['foto' => $this->shape($arquivo)], 201);
    }

    /**
     * Atualiza a legenda (label → original_name) de uma foto do laudo. 200.
     */
    public function updateLabel(UpdateServiceOrderPhotoLabelRequest $request, ServiceOrder $order, Arquivo $arquivo): JsonResponse
    {
        $this->authorize('update', $order);

        $this->guardOwnership($order, $arquivo);

        $arquivo->original_name = (string) $request->validated()['label'];
        $arquivo->save();

        return response()->json(['foto' => $this->shape($arquivo->fresh())]);
    }

    /**
     * Soft-delete de uma foto do laudo. 204 sem body.
     */
    public function destroy(ServiceOrder $order, Arquivo $arquivo): JsonResponse
    {
        $this->authorize('update', $order);

        $this->guardOwnership($order, $arquivo);

        $this->arquivos->softDelete($arquivo);

        return response()->json(null, 204);
    }

    /**
     * Cross-owner guard — o arquivo morphTo PRECISA ser esta ServiceOrder.
     * Fecha /ordens-servico/1/fotos/{arquivo_da_OS_2} mesmo dentro do mesmo business.
     */
    private function guardOwnership(ServiceOrder $order, Arquivo $arquivo): void
    {
        abort_unless(
            $arquivo->arquivable_type === ServiceOrder::class
            && (int) $arquivo->arquivable_id === (int) $order->id,
            404
        );
    }

    /**
     * Shape canônico de Arquivo pra JSON API (espelha frontend LaudoPhotoSection).
     *
     * `label` = original_name (legenda editável). NÃO expõe storage_path/MD5/
     * business_id (defesa em profundidade — display_url é signed se sensitive).
     *
     * @return array{id:int, label:string, mime_type:string, size_bytes:int, display_url:string, created_at:string|null}
     */
    private function shape(Arquivo $arquivo): array
    {
        return [
            'id'          => (int) $arquivo->id,
            'label'       => (string) ($arquivo->original_name ?? ''),
            'mime_type'   => (string) $arquivo->mime_type,
            'size_bytes'  => (int) $arquivo->size_bytes,
            'display_url' => (string) $arquivo->display_url,
            'created_at'  => $arquivo->created_at?->toIso8601String(),
        ];
    }
}
