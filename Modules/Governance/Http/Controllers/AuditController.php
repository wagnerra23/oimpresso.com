<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Governance\Services\AuditDrillDownService;

/**
 * Audit log drill-down — Constituição Art. 9.
 *
 * Filtros: actor, módulo, ação, outcome, período. mcp_audit_log é
 * append-only (trigger MySQL — ADR 0084) então read-only aqui.
 *
 * Export LGPD por business_id (Art. 18 LGPD) fica pra próxima iteração.
 *
 * Refator Wave H (#947): lógica de query extraída pra AuditDrillDownService —
 * Controller só responde HTTP + delega Service + render Inertia (mesma response shape).
 */
class AuditController extends Controller
{
    public function __construct(private readonly AuditDrillDownService $service)
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $period   = $request->input('period', '24h');
        $actor    = $request->input('actor');
        $endpoint = $request->input('endpoint');
        $status   = $request->input('status');

        $filterPayload = [
            'period'   => $period,
            'actor'    => $actor,
            'endpoint' => $endpoint,
            'status'   => $status,
        ];

        // ROLLBACK Wave W7 #953: Inertia::defer quebrava Pages que esperam props eager.
        // (Closure ≠ defer: roda normal no 1º render, só é pulada em partial reload.)
        return Inertia::render('governance/Audit', [
            'entries'             => $this->buildEntriesPayload($filterPayload),
            'kpis'                => $this->buildKpisPayload($filterPayload),
            // closures D-14: distinct por business, não mudam com filtro — pulam no partial reload
            'available_endpoints' => fn () => $this->service->availableEndpoints(),
            'available_actors'    => fn () => $this->service->availableActors(),
            'filters'             => $filterPayload,
        ]);
    }

    /**
     * @param array{period: string, actor: ?string, endpoint: ?string, status: ?string} $filters
     */
    private function buildEntriesPayload(array $filters): mixed
    {
        return $this->service->getRecentEntries(200, $filters);
    }

    /**
     * @param array{period: string, actor: ?string, endpoint: ?string, status: ?string} $filters
     */
    private function buildKpisPayload(array $filters): mixed
    {
        $entries = $this->service->getRecentEntries(200, $filters);

        return $this->service->kpisFor($entries);
    }
}
