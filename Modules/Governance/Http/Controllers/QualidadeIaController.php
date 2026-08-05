<?php

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\MemoriaMetrica;

/**
 * MEM-MET-4 (ADR 0050) — Page /governance/qualidade-ia.
 *
 * PORTE de `Modules\Jana\Http\Controllers\Admin\QualidadeController`
 * (ADR 0366 §D-B, decisão [W] 2026-08-03): eval é **gate de conformidade**,
 * medido contra piso/baseline igual `module-grades` e `drift` — logo o dono
 * é a Governança. A pergunta respondida é "a regra está sendo cumprida?".
 *
 * Trend 7-90d das 8 métricas obrigatórias + 3 RAGAS-aligned, lidas de
 * `copiloto_memoria_metricas` (alimentada pelo cron diário 23:55
 * `copiloto:metrics:apurar` + `copiloto:eval --persist` contra gabarito).
 *
 * O que NÃO mudou de propósito:
 *  - A permissão segue `jana.mcp.usage.all` (Wagner/superadmin). Renomear
 *    permissão exige ADR + migration própria (regra explícita em
 *    Modules/Jana/Http/routes.php).
 *  - A Entity `MemoriaMetrica` e as tabelas (`copiloto_memoria_metricas`,
 *    `jana_memoria_gabarito`) continuam no Jana — só o controller mudou de
 *    dono. Estado intermediário legítimo (ADR 0366 §Consequências); precedente
 *    vivo: Modules/Forja/.../RoadmapController importa Modules\Jana\Entities\Mcp\McpTask.
 *
 * Cross-business é INTENCIONAL aqui: a tela é de PLATAFORMA (superadmin), não
 * do business logado — exceção da Constituição Art. 6+8 preservada pela ADR 0366.
 * A própria métrica `cross_tenant_violations == 0` é o vigia do isolamento.
 *
 * V1: visualização. V2: HITL anotação + drift alerts (Cycle 02).
 *
 * RUNBOOK: memory/requisitos/Governance/RUNBOOK-qualidade-ia.md
 */
class QualidadeIaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:jana.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $dias = (int) max(7, min(90, $request->get('dias', 30)));
        $businessId = $request->get('business_id') !== null
            ? (int) $request->get('business_id')
            : null;

        // Gates ADR 0049/0050 (alvos canônicos) — eager (constante leve, sem DB).
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

        // HOTFIX Wagner 2026-05-25 PRESERVADO no porte: `Inertia::defer` fica FORA
        // de series/kpis. A Page desestrutura direto (`series.map`, `kpis.map`) —
        // defer entrega undefined no primeiro render → TypeError `undefined.filter`
        // em prod (mesmo padrão PR #1550/#1552). Reintroduzir SÓ junto com
        // `<Deferred data={['series','kpis']} fallback={...}>` no frontend.
        return Inertia::render('governance/QualidadeIa', [
            'filtros' => ['dias' => $dias, 'business_id' => $businessId],
            'gates'   => $gates,
            'series'  => $this->buildSeriesPayload($dias, $businessId),
            'kpis'    => $this->buildKpisPayload($dias, $businessId),
            // closure D-14 (NÃO é defer): gabarito da plataforma não muda com filtro,
            // então pula no partial reload (only: series/kpis/filtros). Roda normal no
            // load cheio — por isso não cai na armadilha do defer acima.
            'gabarito_total' => fn () => DB::table('jana_memoria_gabarito')->where('ativo', true)->count(),
            'gabarito_por_categoria' => fn () => DB::table('jana_memoria_gabarito')
                ->where('ativo', true)
                ->select('categoria', DB::raw('COUNT(*) as c'))
                ->groupBy('categoria')
                ->pluck('c', 'categoria'),
        ]);
    }

    /** Trend série agrupada por business. */
    private function buildSeriesPayload(int $dias, ?int $businessId): array
    {
        $query = MemoriaMetrica::query()
            ->ultimosDias($dias)
            ->orderBy('apurado_em');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $rows = $query->get();

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

        return array_values($series);
    }

    /** KPIs — última métrica por business. */
    private function buildKpisPayload(int $dias, ?int $businessId): array
    {
        $series = $this->buildSeriesPayload($dias, $businessId);

        $kpis = [];
        foreach ($series as $s) {
            $ultimo = end($s['pontos']);
            if ($ultimo === false) {
                continue;
            }
            $kpis[] = [
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

        return $kpis;
    }
}
