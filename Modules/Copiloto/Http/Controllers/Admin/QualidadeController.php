<?php

namespace Modules\Copiloto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Copiloto\Entities\MemoriaMetrica;

/**
 * MEM-MET-4 (ADR 0050) — Page /copiloto/admin/qualidade.
 *
 * Trend 30d das 8 métricas obrigatórias + 3 RAGAS-aligned, lidos de
 * copiloto_memoria_metricas (alimentado pelo cron diário 23:55 +
 * copiloto:eval --persist contra gabarito).
 *
 * Permite Wagner ver ao vivo se Recall@3 está acima do gate ADR 0049
 * (>0.80) e calibrar HyDE/Reranker/RRF.
 *
 * V1: visualização. V2: HITL anotação + drift alerts (Cycle 02).
 *
 * Permissão: copiloto.mcp.usage.all (Wagner/superadmin).
 */
class QualidadeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $dias = (int) max(7, min(90, $request->get('dias', 30)));
        $businessId = $request->get('business_id') !== null
            ? (int) $request->get('business_id')
            : null;

        // Trend série
        $query = MemoriaMetrica::query()
            ->ultimosDias($dias)
            ->orderBy('apurado_em');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $rows = $query->get();

        // Agrupar por business pra desenhar séries
        $series = [];
        foreach ($rows as $r) {
            $bizKey = $r->business_id === null ? 'plataforma' : "biz_{$r->business_id}";
            if (!isset($series[$bizKey])) {
                $series[$bizKey] = [
                    'business_id' => $r->business_id,
                    'label'       => $r->business_id === null ? 'Plataforma' : "Business #{$r->business_id}",
                    'pontos'      => [],
                ];
            }
            $series[$bizKey]['pontos'][] = [
                'data'                  => optional($r->apurado_em)->toDateString(),
                'recall_at_3'           => $r->recall_at_3 !== null ? (float) $r->recall_at_3 : null,
                'precision_at_3'        => $r->precision_at_3 !== null ? (float) $r->precision_at_3 : null,
                'mrr'                   => $r->mrr !== null ? (float) $r->mrr : null,
                'latencia_p95_ms'       => $r->latencia_p95_ms !== null ? (int) $r->latencia_p95_ms : null,
                'tokens_medio'          => $r->tokens_medio_interacao !== null ? (int) $r->tokens_medio_interacao : null,
                'memory_bloat'          => $r->memory_bloat_ratio !== null ? (float) $r->memory_bloat_ratio : null,
                'taxa_contradicoes_pct' => $r->taxa_contradicoes_pct !== null ? (float) $r->taxa_contradicoes_pct : null,
                'cross_tenant_violations' => (int) $r->cross_tenant_violations,
                'faithfulness'          => $r->faithfulness !== null ? (float) $r->faithfulness : null,
                'answer_relevancy'      => $r->answer_relevancy !== null ? (float) $r->answer_relevancy : null,
                'context_precision'     => $r->context_precision !== null ? (float) $r->context_precision : null,
                'total_interacoes_dia'  => (int) $r->total_interacoes_dia,
                'total_memorias_ativas' => (int) $r->total_memorias_ativas,
            ];
        }

        // Última métrica por business (KPIs)
        $kpis = [];
        foreach ($series as $key => $s) {
            $ultimo = end($s['pontos']);
            if ($ultimo === false) continue;
            $kpis[$key] = [
                'business_id' => $s['business_id'],
                'label'       => $s['label'],
                'apurado_em'  => $ultimo['data'],
                'recall_at_3' => $ultimo['recall_at_3'],
                'precision_at_3' => $ultimo['precision_at_3'],
                'mrr'         => $ultimo['mrr'],
                'faithfulness' => $ultimo['faithfulness'],
                'latencia_p95_ms' => $ultimo['latencia_p95_ms'],
                'tokens_medio' => $ultimo['tokens_medio'],
                'taxa_contradicoes_pct' => $ultimo['taxa_contradicoes_pct'],
                'cross_tenant_violations' => $ultimo['cross_tenant_violations'],
                'total_interacoes_dia' => $ultimo['total_interacoes_dia'],
            ];
        }

        // Gates ADR 0049/0050 (alvos canônicos)
        $gates = [
            'recall_at_3'           => ['op' => '>=', 'alvo' => 0.80, 'unit' => '',  'label' => 'Recall@3'],
            'precision_at_3'        => ['op' => '>=', 'alvo' => 0.60, 'unit' => '',  'label' => 'Precision@3'],
            'mrr'                   => ['op' => '>=', 'alvo' => 0.70, 'unit' => '',  'label' => 'MRR'],
            'faithfulness'          => ['op' => '>=', 'alvo' => 0.85, 'unit' => '',  'label' => 'Faithfulness'],
            'latencia_p95_ms'       => ['op' => '<=', 'alvo' => 2000, 'unit' => 'ms','label' => 'Latência p95'],
            'tokens_medio'          => ['op' => '<=', 'alvo' => 3000, 'unit' => 'tk','label' => 'Tokens/interação'],
            'memory_bloat'          => ['op' => '>=', 'alvo' => 0.60, 'unit' => '',  'label' => 'Bloat ratio'],
            'taxa_contradicoes_pct' => ['op' => '<=', 'alvo' => 2.0,  'unit' => '%', 'label' => 'Contradições'],
            'cross_tenant_violations' => ['op' => '==', 'alvo' => 0,  'unit' => '',  'label' => 'Cross-tenant'],
        ];

        // Lista de businesses pro filtro
        $businesses = DB::table('mcp_memory_documents')
            ->whereNotNull('module')
            ->select('module')
            ->distinct()
            ->pluck('module');

        return Inertia::render('Copiloto/Admin/Qualidade/Index', [
            'series'         => array_values($series),
            'kpis'           => array_values($kpis),
            'gates'          => $gates,
            'filtros'        => ['dias' => $dias, 'business_id' => $businessId],
            'gabarito_total' => DB::table('copiloto_memoria_gabarito')->where('ativo', true)->count(),
            'gabarito_por_categoria' => DB::table('copiloto_memoria_gabarito')
                ->where('ativo', true)
                ->select('categoria', DB::raw('COUNT(*) as c'))
                ->groupBy('categoria')
                ->pluck('c', 'categoria'),
        ]);
    }
}
