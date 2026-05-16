<?php

namespace Modules\Jana\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Services\GovernancaService;

/**
 * MEM-MCP-1.e (ADR 0053) — Dashboard de governança do MCP server.
 *
 * Visualiza o consumo cross-team do MCP server: quantas calls, por quem,
 * pra quais tools, com qual latência e custo. Diferente de CustosController
 * (US-COPI-070) que mostra IA do CHAT por business — aqui é MCP cross-team.
 *
 * Permissão: copiloto.mcp.usage.all (Wagner/superadmin por padrão).
 *
 * Fontes de dados:
 *   - mcp_audit_log: cada chamada MCP (append-only, 1 ano retenção)
 *   - mcp_usage_diaria: agregações diárias (alimentadas pelo cron 23:55)
 */
class GovernancaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request, GovernancaService $service): Response
    {
        $preset = $request->get('preset', '30d');
        if (! in_array($preset, ['hoje', 'ontem', '7d', '30d', 'mes_anterior', 'custom'], true)) {
            $preset = '30d';
        }

        $range = $service->resolverPeriodo(
            $preset,
            $request->get('de'),
            $request->get('ate'),
        );

        // D6.a Wave 17 — painel pesado (7 agregações cross-team) defer'd em chunks.
        // Cada agregação roda em closure separada pra Inertia partial reload funcionar.
        // Front wrap em `<Deferred data="kpis,por_status,..." fallback={skeleton}>`.
        $painelCallable = fn () => $service->painel($range['inicio'], $range['fim']);

        return Inertia::render('Jana/Admin/Governanca/Index', [
            'periodo' => $range,  // eager — sem DB
            'filters' => [
                'preset' => $preset,
                'de'     => $request->get('de'),
                'ate'    => $request->get('ate'),
            ],
            // D6.a Wave 17 — 7 props deferred (todas DB-bound).
            'kpis'              => Inertia::defer(fn () => $painelCallable()['kpis']),
            'por_status'        => Inertia::defer(fn () => $painelCallable()['por_status']),
            'latency'           => Inertia::defer(fn () => $painelCallable()['latency']),
            'top_tools'         => Inertia::defer(fn () => $painelCallable()['top_tools']),
            'top_users'         => Inertia::defer(fn () => $painelCallable()['top_users']),
            'denied_por_codigo' => Inertia::defer(fn () => $painelCallable()['denied_por_codigo']),
            'serie_diaria'      => Inertia::defer(fn () => $painelCallable()['serie_diaria']),
        ]);
    }
}
