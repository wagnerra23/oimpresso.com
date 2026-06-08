<?php

declare(strict_types=1);

namespace Modules\KB\Services;

use App\Util\OtelHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\KB\Entities\KbNode;

/**
 * KbArticleService — thin extraction de filter/paginate logic de KbNodeController.
 *
 * Razão: D4.a Services/Controllers ratio gap (Wave J 2026-05-16 — boost Modules/KB
 * 59→meta 70). KbNodeController concentrava filter building + pagination — extraído
 * pra Service testável independente.
 *
 * ZERO regressão: controller injeta Service via DI e delega `index()` query build;
 * mesma resposta JSON, mesmos filters suportados.
 *
 * Contrato endpoints: memory/requisitos/KB/SCHEMA-DB-V1.md §11
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - KbNode usa BelongsToBusinessTrait → global scope automático.
 *   - Service NÃO usa withoutGlobalScopes (preserva isolation).
 *   - Cross-tenant access retorna 404 via firstOrFail no controller.
 */
class KbArticleService
{
    /**
     * Constrói query de listagem de kb_nodes aplicando filters do Request.
     *
     * Suporta: type, category, subcategory, q (busca texto), pinned, editable_only, bridge_only.
     *
     * Aliases scope esperados em KbNode (já existentes):
     *   - active(), ofType(string), search(string), pinned(), editable(), bridge()
     */
    public function buildListQuery(Request $request): Builder
    {
        $q = KbNode::query()->active();

        if ($type = $request->string('type')->toString()) {
            $q->ofType($type);
        }

        if ($cat = $request->integer('category')) {
            $q->where('category_id', $cat);
        }

        if ($sub = $request->integer('subcategory')) {
            $q->where('subcategory_id', $sub);
        }

        if ($search = trim((string) $request->get('q', ''))) {
            $q->search($search);
        }

        if ($request->boolean('pinned')) {
            $q->pinned();
        }

        if ($request->boolean('editable_only')) {
            $q->editable();
        }

        if ($request->boolean('bridge_only')) {
            $q->bridge();
        }

        return $q;
    }

    /**
     * Pagina o resultado aplicando ordenação canônica (pinned DESC, updated_at DESC).
     *
     * per_page bounded [5, 100], default 25.
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = (int) min(100, max(5, $request->integer('per_page', 25)));

        // Wave 25 — OTel span (ADR 0155 D9.a). Pagination é hot-path do KB browser;
        // útil pra observar p95 latency por business + filtros aplicados.
        // Zero-cost se config('otel.enabled')=false.
        return OtelHelper::spanBiz('kb.article.paginate', function () use ($request, $perPage) {
            return $this->buildListQuery($request)
                ->orderByDesc('pinned')
                ->orderByDesc('updated_at')
                ->paginate($perPage)
                ->withQueryString();
        }, [
            'module'   => 'KB',
            'per_page' => $perPage,
            'has_q'    => $request->filled('q'),
            'type'     => $request->string('type')->toString() ?: 'any',
        ]);
    }
}
