<?php

namespace Modules\Compras\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Compras\Http\Requests\ListarComprasRequest;
use Modules\Compras\Services\ComprasService;

/**
 * ComprasController — Wave 3 backend wrapper (US-COM-001).
 *
 * F6 Soft wrapper Inertia leve sobre tabela `transactions` polimórfica
 * (type='purchase'/'purchase_order'/'purchase_return'). Reusa TransactionUtil
 * + TransactionObserver Financeiro já existentes — NÃO duplica.
 *
 * Props caras (`rows` paginação + `kpis` agregados) servidas via Inertia::defer
 * conforme [pattern obrigatório](resources/css/cowork-compras-bundle.css).
 *
 * Tier 0 multi-tenant ADR 0093 — toda query deriva de session('user.business_id')
 * passado explicitamente pro ComprasService.
 *
 * @see memory/requisitos/Compras/SPEC.md
 * @see memory/sessions/2026-05-21-como-integrar-compras.md
 */
class ComprasController extends Controller
{
    public function __construct(
        protected ModuleUtil $moduleUtil,
        protected ComprasService $comprasService,
    ) {}

    /**
     * Cockpit Compras — lista paginada + 4 KPIs FSM (aberto/transito/mes/fornec).
     *
     * Tier 0 defense-in-depth (audit sênior 2026-05-25 Gap #2):
     *  - business_id vem de `auth()->user()->business_id` (não session — defesa
     *    contra session spoofing). Layer 1 do pattern Laravel SaaS 2026.
     *  - `abort_if($businessId <= 0)` guard explícito — `(int) null = 0` não
     *    passa silencioso.
     *  - Cross-check session === auth pra detectar drift.
     *
     * Validação inline substituída por `ListarComprasRequest` (Gap #4) —
     * whitelist allow-only de filters/sort/dir/per_page anti-SQLi.
     */
    public function index(ListarComprasRequest $request)
    {
        $user = auth()->user();
        $businessId = (int) ($user->business_id ?? 0);

        abort_if($businessId <= 0, 403, 'Business inválido — usuário sem business associado');

        // Cross-check defense-in-depth (layer 2)
        $sessionBiz = (int) session('user.business_id', 0);
        if ($sessionBiz > 0 && $sessionBiz !== $businessId) {
            abort(403, "Business drift detectado (auth={$businessId}, session={$sessionBiz})");
        }

        $filters = $request->filtros();
        $compraId = $request->compraId();

        // C1 convergência (ADR compras-purchase-convergencia-c1) — botão "+ Nova
        // compra" e AcoesDropdown "Editar/Excluir" delegam /purchases/* Inertia
        // via router.visit. Permissions vêm do trilho A (Purchase MWART Wave 2
        // B5), não compras.* — alias só em V2 se Wagner mantiver módulo como
        // conceito separado.

        return Inertia::render('Compras/Index', [
            'filters' => $filters,

            'selected_id' => $compraId ?: null,

            'permissions' => [
                'create' => $user->can('purchase.create'),
                'update' => $user->can('purchase.update'),
                'delete' => $user->can('purchase.delete'),
            ],

            'kpis' => Inertia::defer(
                fn () => $this->comprasService->calcularKpis($businessId)
            ),

            'rows' => Inertia::defer(
                fn () => $this->buildRowsPayload($businessId, $filters)
            ),

            'summary' => Inertia::defer(
                fn () => $this->comprasService->calcularSummary($businessId, $filters)
            ),

            'compra_detalhe' => Inertia::defer(
                fn () => $compraId ? $this->comprasService->buscarDetalhe($compraId, $businessId) : null
            ),
        ]);
    }

    /**
     * Detalhe single — Wave 5 endpoint pra DrawerView 5 tabs.
     *
     * Partial reload via `router.get('/compras', { compra_id: X }, { only: ['compra_detalhe'] })`
     * mantém a tabela em cache cliente-side; só `compra_detalhe` chega da rede.
     *
     * Tier 0 ADR 0093 — business_id scope no Service ANTES de qualquer fetch.
     */
    public function show(Request $request, int $id)
    {
        if (! auth()->user()->can('compras.view')) {
            abort(403);
        }

        // Tier 0 defense-in-depth (audit sênior 2026-05-25 Gap #2)
        $businessId = (int) (auth()->user()->business_id ?? 0);
        abort_if($businessId <= 0, 403, 'Business inválido');

        $detalhe = $this->comprasService->buscarDetalhe($id, $businessId);

        // Defense-in-depth: 404 (não 403) — não revelar existência de compra
        // de outro business (pattern Tier 0 audit sênior §3.1.4)
        if (! $detalhe) {
            abort(404);
        }

        return response()->json($detalhe);
    }

    /**
     * Paginação canônica — 25 linhas/página.
     *
     * Retorna {data:[], links:[...], meta:{current_page,total,...}} compatível
     * com `<Pagination>` Inertia helper.
     */
    private function buildRowsPayload(int $businessId, array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 25);
        $paginator = $this->comprasService
            ->listarCompras($businessId, $filters)
            ->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
