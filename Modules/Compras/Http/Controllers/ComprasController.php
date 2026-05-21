<?php

namespace Modules\Compras\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

/**
 * ComprasController — Wave 1 scaffold (US-COM-001).
 *
 * F6 Soft wrapper Inertia leve sobre tabela `transactions` polimórfica
 * (type='purchase'/'purchase_order'/'purchase_return'). Reusa TransactionUtil
 * + TransactionObserver Financeiro já existentes — NÃO duplica.
 *
 * Wave 1 entrega apenas `index()` stub renderizando Page Inertia vazia (KPIs +
 * rows como `null` placeholders). Wave 3 popula via `buildKpisPayload()` +
 * `buildRowsPayload()` com Inertia::defer.
 *
 * Tier 0 multi-tenant ADR 0093 — toda query deriva de session('user.business_id').
 *
 * @see memory/requisitos/Compras/SPEC.md
 * @see memory/sessions/2026-05-21-como-integrar-compras.md
 */
class ComprasController extends Controller
{
    public function __construct(protected ModuleUtil $moduleUtil) {}

    /**
     * Cockpit Compras — lista paginada + 4 KPIs FSM (aberto/transito/mes/fornec).
     *
     * Wave 1: stub Inertia render sem payloads reais. Wave 3 conecta TransactionUtil.
     */
    public function index(Request $request)
    {
        if (! auth()->user()->can('compras.view')) {
            abort(403);
        }

        return Inertia::render('Compras/Index', [
            'kpis' => null,
            'rows' => null,
            'filters' => [
                'q' => $request->query('q', ''),
                'stage' => $request->query('stage', 'all'),
            ],
        ]);
    }
}
