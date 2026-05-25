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

        // Wagner 2026-05-25 HOTFIX: removido Inertia::defer (7 props).
        // Causa: Governanca/Index.tsx destruct direto sem wrap <Deferred> nem
        // fallback (TypeError `undefined.find` capturado no smoke browser
        // MCP). Mesmo padrão dos PRs #1550 / #1552 / fix Painel/Qualidade/
        // Roadmap nesta onda. Chama service UMA vez (não 7×) e passa
        // payloads direto. Perde TTFB otimizado, mas evita 100% das renders
        // crasharem em prod. Reintroduzir defer quando frontend ganhar
        // `<Deferred data="kpis,por_status,..." fallback={...}>` wrap
        // (refactor MWART futuro).
        $painel = $service->painel($range['inicio'], $range['fim']);

        return Inertia::render('Jana/Admin/Governanca/Index', [
            'periodo' => $range,  // eager — sem DB
            'filters' => [
                'preset' => $preset,
                'de'     => $request->get('de'),
                'ate'    => $request->get('ate'),
            ],
            'kpis'              => $painel['kpis'],
            'por_status'        => $painel['por_status'],
            'latency'           => $painel['latency'],
            'top_tools'         => $painel['top_tools'],
            'top_users'         => $painel['top_users'],
            'denied_por_codigo' => $painel['denied_por_codigo'],
            'serie_diaria'      => $painel['serie_diaria'],
        ]);
    }
}
