<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MVP-1 — Learning Pipeline visual (Wagner Cognitive Control).
 *
 * Mostra contagem de decisions em cada estágio do loop:
 *   Capturado → Classificado → Roteado → Em execução → Reviewed → Pattern → Promoted
 *
 * Cada estágio é clicável → filtro em /ads/admin/decisoes.
 */
class LearningController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        // Janela de análise: últimas 24h
        $since = now()->subDay();
        $base = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since);

        $stages = [
            [
                'key'         => 'captured',
                'name'        => 'Capturado',
                'description' => 'Evento detectado pelo Brain A daemon ou submetido via API',
                'count'       => (clone $base)->count(),
                'filter_url'  => '/ads/admin/decisoes?tab=historico',
                'icon'        => 'inbox',
                'color'       => 'zinc',
            ],
            [
                'key'         => 'classified',
                'name'        => 'Classificado',
                'description' => 'Triage rule-based ou Ollama classificou em event_type canônico',
                'count'       => (clone $base)->whereNotNull('event_type')->count(),
                'filter_url'  => null,
                'icon'        => 'tag',
                'color'       => 'blue',
            ],
            [
                'key'         => 'routed',
                'name'        => 'Roteado',
                'description' => 'DecisionRouter aplicou Policy + Risk + Confidence',
                'count'       => (clone $base)->whereNotNull('destination')->count(),
                'filter_url'  => null,
                'icon'        => 'split',
                'color'       => 'indigo',
            ],
            [
                'key'         => 'pending_brain_b',
                'name'        => 'Aguardando Brain B',
                'description' => 'Cron processa em até 5min',
                'count'       => (clone $base)->where('destination', 'brain_b')->where('brain_used', 'none')->count(),
                'filter_url'  => '/ads/admin/decisoes?tab=em_andamento',
                'icon'        => 'hourglass',
                'color'       => 'amber',
            ],
            [
                'key'         => 'pending_human',
                'name'        => 'Aguardando humano',
                'description' => 'HiTL-2 ou HiTL-3, Wagner decide',
                'count'       => (clone $base)->where('destination', 'pending_wagner')->where('outcome', 'cancelled')->count(),
                'filter_url'  => '/ads/admin/decisoes?tab=pendentes',
                'icon'        => 'user-check',
                'color'       => 'orange',
            ],
            [
                'key'         => 'executed',
                'name'        => 'Executado',
                'description' => 'Wagner aprovou ou Brain A executou autônomo',
                'count'       => (clone $base)->where('outcome', 'success')->count(),
                'filter_url'  => '/ads/admin/decisoes?tab=historico',
                'icon'        => 'check-circle-2',
                'color'       => 'emerald',
            ],
            [
                'key'         => 'reviewed',
                'name'        => 'Reviewed',
                'description' => 'ReviewerAgent G-Eval atribuiu score 0-100',
                'count'       => (clone $base)->whereNotNull('review_score')->count(),
                'filter_url'  => null,
                'icon'        => 'star',
                'color'       => 'purple',
            ],
            [
                'key'         => 'pattern_recorded',
                'name'        => 'Pattern registrado',
                'description' => 'Outcome contado em mcp_decision_patterns (Wilson Score)',
                'count'       => DB::table('mcp_decision_patterns')->where('business_id', $businessId)->where('updated_at', '>=', $since)->count(),
                'filter_url'  => '/ads/admin/skills',
                'icon'        => 'zap',
                'color'       => 'yellow',
            ],
            [
                'key'         => 'promotion_ready',
                'name'        => 'Pronto pra promoção',
                'description' => 'Wilson LB ≥ 0.80 e ≥10 amostras — candidato a hardcoded',
                'count'       => DB::table('mcp_decision_patterns')
                    ->where('business_id', $businessId)
                    ->where('total_count', '>=', 10)
                    ->where('success_rate', '>=', 0.85) // approximação rápida
                    ->where('is_hardcoded', false)
                    ->count(),
                'filter_url'  => '/ads/admin/skills',
                'icon'        => 'trending-up',
                'color'       => 'green',
            ],
        ];

        // Throughput por hora (últimas 24h)
        $throughput = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hora"),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when outcome = 'success' then 1 else 0 end) as sucessos"),
                DB::raw("sum(case when outcome = 'wagner_rejected' then 1 else 0 end) as rejeitadas")
            )
            ->groupBy('hora')
            ->orderBy('hora')
            ->get()
            ->map(fn ($r) => [
                'hora'       => $r->hora,
                'total'      => (int) $r->total,
                'sucessos'   => (int) $r->sucessos,
                'rejeitadas' => (int) $r->rejeitadas,
            ])
            ->all();

        $kpis = [
            'janela_horas'    => 24,
            'eventos_24h'     => $stages[0]['count'],
            'taxa_review'     => $stages[0]['count'] > 0
                ? round(($stages[6]['count'] / $stages[0]['count']) * 100, 1)
                : 0,
            'taxa_pattern'    => $stages[0]['count'] > 0
                ? round(($stages[7]['count'] / $stages[0]['count']) * 100, 1)
                : 0,
            'pendencia_humana' => $stages[4]['count'],
        ];

        return Inertia::render('ads/Admin/Learning', [
            'stages'     => $stages,
            'throughput' => $throughput,
            'kpis'       => $kpis,
        ]);
    }
}
