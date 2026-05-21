<?php

namespace Modules\Compras\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
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
     */
    public function index(Request $request)
    {
        if (! auth()->user()->can('compras.view')) {
            abort(403);
        }

        $businessId = (int) session('user.business_id');
        $filters = [
            'q' => $request->query('q', ''),
            'stage' => $request->query('stage', 'all'),
        ];

        return Inertia::render('Compras/Index', [
            'filters' => $filters,

            'kpis' => Inertia::defer(
                fn () => $this->comprasService->calcularKpis($businessId)
            ),

            'rows' => Inertia::defer(
                fn () => $this->buildRowsPayload($businessId, $filters)
            ),
        ]);
    }

    /**
     * Paginação canônica — 25 linhas/página.
     *
     * Retorna {data:[], links:[...], meta:{current_page,total,...}} compatível
     * com `<Pagination>` Inertia helper.
     */
    private function buildRowsPayload(int $businessId, array $filters): array
    {
        $paginator = $this->comprasService
            ->listarCompras($businessId, $filters)
            ->paginate(25);

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
