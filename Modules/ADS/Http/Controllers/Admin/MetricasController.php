<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MetricasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);
        $base = DB::table('mcp_dual_brain_decisions')->where('business_id', $businessId);

        $total      = (clone $base)->count();
        $last30d    = (clone $base)->where('created_at', '>=', now()->subDays(30))->count();
        $brain_a    = (clone $base)->where('destination', 'brain_a')->count();
        $brain_b    = (clone $base)->where('destination', 'brain_b')->count();
        $blocked    = (clone $base)->where('destination', 'blocked')->count();
        $pending    = (clone $base)->where('destination', 'pending_wagner')->count();

        $taxa_autonomia = $total > 0 ? round(($brain_a / $total) * 100, 1) : 0;
        $taxa_humano    = $total > 0 ? round(($pending / $total) * 100, 1) : 0;
        $taxa_firewall  = $total > 0 ? round(($blocked / $total) * 100, 1) : 0;

        $modificadas = (clone $base)->where('outcome', 'wagner_modified')->count();
        $rejeitadas  = (clone $base)->where('outcome', 'wagner_rejected')->count();
        $sucessos    = (clone $base)->where('outcome', 'success')->count();

        // Custo Brain B (Sonnet) — baseado em tokens_used e cost_usd se preenchido
        $custo_total = (float) (clone $base)->sum('cost_usd') ?? 0.0;
        $tokens_total = (int) (clone $base)->sum('tokens_used') ?? 0;

        $por_dominio = (clone $base)
            ->select('domain', DB::raw('count(*) as n'))
            ->groupBy('domain')
            ->orderByDesc('n')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['domain' => $r->domain, 'count' => (int) $r->n])
            ->values();

        $por_event_type = (clone $base)
            ->select('event_type', DB::raw('count(*) as n'))
            ->groupBy('event_type')
            ->orderByDesc('n')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['event_type' => $r->event_type, 'count' => (int) $r->n])
            ->values();

        return Inertia::render('ads/Admin/Metricas', [
            'kpis' => [
                'total'           => $total,
                'last30d'         => $last30d,
                'taxa_autonomia'  => $taxa_autonomia,
                'taxa_humano'     => $taxa_humano,
                'taxa_firewall'   => $taxa_firewall,
                'modificadas'     => $modificadas,
                'rejeitadas'      => $rejeitadas,
                'sucessos'        => $sucessos,
                'custo_total_usd' => round($custo_total, 4),
                'tokens_total'    => $tokens_total,
            ],
            'distribuicao' => [
                'brain_a' => $brain_a,
                'brain_b' => $brain_b,
                'blocked' => $blocked,
                'pending' => $pending,
            ],
            'por_dominio'    => $por_dominio,
            'por_event_type' => $por_event_type,
        ]);
    }
}
