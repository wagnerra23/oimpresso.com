<?php

namespace Modules\Copiloto\Services\Metricas;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Entities\MemoriaMetrica;

/**
 * MEM-MET-2 (ADR 0050+0051) — Apurador das 8 métricas obrigatórias + 3 RAGAS-aligned.
 *
 * Estratégia de fonte por métrica:
 *  - latencia_p95_ms / latência → log `otel-gen-ai` (JSON lines, atributo gen_ai.response.duration_ms)
 *  - tokens_medio_interacao → DB copiloto_mensagens.tokens_in + tokens_out (assistant role)
 *  - total_interacoes_dia → DB copiloto_mensagens role=user no dia
 *  - total_memorias_ativas → DB copiloto_memoria_facts valid_until IS NULL
 *  - memory_bloat_ratio → heurística: % fatos com valid_from <= 30d / total
 *  - taxa_contradicoes_pct → heurística: pares de fatos ativos similar+mesmo user que deveriam ter superseded
 *  - cross_tenant_violations → 0 default (audit trimestral via red-team)
 *  - recall_at_3 / precision_at_3 / mrr / faithfulness / answer_relevancy / context_precision → NULL
 *    (precisam de golden set MEM-P2-1; ficam null até comando ser invocado com --golden=path)
 *
 * Multi-tenant: apura por business_id ou para a plataforma agregada (NULL).
 * Idempotente: upsert via unique (apurado_em, business_id) na tabela.
 */
class MetricasApurador
{
    public function __construct(
        protected ?string $logChannel = 'otel-gen-ai',
    ) {
    }

    /**
     * Apura todas as métricas auto-deriváveis e faz upsert na tabela.
     * Retorna a Entity persistida.
     *
     * @param int|null $businessId  Tenant a apurar (null = plataforma agregada)
     * @param string|null $apuradoEm  Data alvo no formato YYYY-MM-DD (default: hoje)
     */
    public function apurar(?int $businessId, ?string $apuradoEm = null): MemoriaMetrica
    {
        $data = CarbonImmutable::parse($apuradoEm ?? now()->toDateString())->startOfDay();

        $latencia       = $this->latenciaP95Ms($businessId, $data);
        $tokensMedios   = $this->tokensMedioInteracao($businessId, $data);
        $totalInter     = $this->totalInteracoesDia($businessId, $data);
        $totalMemorias  = $this->totalMemoriasAtivas($businessId, $data);
        $bloat          = $this->memoryBloatRatio($businessId, $data);
        $contradicoes   = $this->taxaContradicoesPct($businessId, $data);

        $payload = [
            'apurado_em'              => $data->toDateString(),
            'business_id'             => $businessId,
            'latencia_p95_ms'         => $latencia,
            'tokens_medio_interacao'  => $tokensMedios,
            'total_interacoes_dia'    => $totalInter,
            'total_memorias_ativas'   => $totalMemorias,
            'memory_bloat_ratio'      => $bloat,
            'taxa_contradicoes_pct'   => $contradicoes,
            'cross_tenant_violations' => 0, // audit trimestral
            'detalhes'                => [
                'apurado_at' => now()->toIso8601String(),
                'fonte'      => [
                    'latencia'       => 'log:' . $this->logChannel,
                    'tokens_medios'  => 'db:copiloto_mensagens',
                    'total_interacoes' => 'db:copiloto_mensagens',
                    'total_memorias' => 'db:copiloto_memoria_facts',
                    'bloat'          => 'heurística:30d',
                    'contradicoes'   => 'heurística:dup_user_ativos',
                ],
                'observacao' => 'Métricas RAGAS (Recall@3/MRR/faithfulness/...) ficam NULL até golden set MEM-P2-1.',
            ],
        ];

        $linha = MemoriaMetrica::updateOrCreate(
            ['apurado_em' => $data->toDateString(), 'business_id' => $businessId],
            $payload,
        );

        Log::channel('copiloto-ai')->info('MetricasApurador::apurar', [
            'business_id'        => $businessId,
            'apurado_em'         => $data->toDateString(),
            'latencia_p95_ms'    => $latencia,
            'tokens_medios'      => $tokensMedios,
            'total_interacoes'   => $totalInter,
            'total_memorias'     => $totalMemorias,
        ]);

        return $linha;
    }

    /**
     * Lê o log `otel-gen-ai-{date}.log`, extrai eventos `gen_ai.span` do dia
     * filtrados pelo business_id, calcula p95 do `gen_ai.response.duration_ms`.
     * Retorna null se não houver eventos (não é zero — distinção semântica).
     */
    public function latenciaP95Ms(?int $businessId, CarbonImmutable $data): ?int
    {
        $duracoes = $this->coletarDuracoesDoLog($businessId, $data);

        if (count($duracoes) === 0) {
            return null;
        }

        sort($duracoes);
        $idx95 = (int) ceil(0.95 * count($duracoes)) - 1;
        $idx95 = max(0, min($idx95, count($duracoes) - 1));

        return (int) $duracoes[$idx95];
    }

    /**
     * Tokens médios = média de (tokens_in + tokens_out) para mensagens assistant
     * no dia, filtradas por conversa do business.
     * Retorna null se não houver mensagens registradas.
     */
    public function tokensMedioInteracao(?int $businessId, CarbonImmutable $data): ?int
    {
        $q = DB::table('copiloto_mensagens as m')
            ->join('copiloto_conversas as c', 'c.id', '=', 'm.conversa_id')
            ->where('m.role', 'assistant')
            ->whereDate('m.created_at', $data->toDateString())
            ->whereNotNull('m.tokens_in')
            ->whereNotNull('m.tokens_out');

        if ($businessId !== null) {
            $q->where('c.business_id', $businessId);
        }

        $avg = $q->avg(DB::raw('m.tokens_in + m.tokens_out'));

        return $avg !== null ? (int) round((float) $avg) : null;
    }

    /**
     * Total de interações do dia = mensagens role=user.
     */
    public function totalInteracoesDia(?int $businessId, CarbonImmutable $data): int
    {
        $q = DB::table('copiloto_mensagens as m')
            ->join('copiloto_conversas as c', 'c.id', '=', 'm.conversa_id')
            ->where('m.role', 'user')
            ->whereDate('m.created_at', $data->toDateString());

        if ($businessId !== null) {
            $q->where('c.business_id', $businessId);
        }

        return (int) $q->count();
    }

    /**
     * Total de memórias ativas no fim do dia (valid_until IS NULL e não soft-deleted).
     */
    public function totalMemoriasAtivas(?int $businessId, CarbonImmutable $data): int
    {
        $q = DB::table('copiloto_memoria_facts')
            ->whereNull('valid_until')
            ->whereNull('deleted_at')
            ->whereDate('valid_from', '<=', $data->endOfDay()->toDateTimeString());

        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        return (int) $q->count();
    }

    /**
     * Memory bloat ratio (heurística inicial — refinará quando tivermos
     * tracking de "fato citado em recall"):
     *   ativos_recentes (valid_from <= 30d e ativos) / total_ativos
     *
     * Meta > 0.60 do ADR 0050 — fatos antigos são naturalmente "menos úteis".
     * Em produção, calibrar com dados reais. Retorna null se total = 0.
     */
    public function memoryBloatRatio(?int $businessId, CarbonImmutable $data): ?float
    {
        $base = DB::table('copiloto_memoria_facts')
            ->whereNull('valid_until')
            ->whereNull('deleted_at');

        if ($businessId !== null) {
            $base->where('business_id', $businessId);
        }

        $total = (int) (clone $base)->count();
        if ($total === 0) {
            return null;
        }

        $recentes = (int) (clone $base)
            ->where('valid_from', '>=', $data->subDays(30)->toDateTimeString())
            ->count();

        return round($recentes / $total, 3);
    }

    /**
     * Taxa de contradições (heurística): pares de fatos ativos no mesmo
     * (business_id, user_id) que tenham fato com substring igual mas
     * `valid_until` NULL em ambos — sinal de que um deveria ter sido
     * superseded mas o pipeline de extração não pegou.
     *
     * Implementação MVP: conta linhas que têm gêmea (mesmo prefixo 30 chars)
     * dentro do mesmo (biz, user). Refina com NLI/embeddings depois.
     */
    public function taxaContradicoesPct(?int $businessId, CarbonImmutable $data): ?float
    {
        $base = DB::table('copiloto_memoria_facts')
            ->whereNull('valid_until')
            ->whereNull('deleted_at')
            ->whereDate('valid_from', '<=', $data->endOfDay()->toDateTimeString());

        if ($businessId !== null) {
            $base->where('business_id', $businessId);
        }

        $total = (int) (clone $base)->count();
        if ($total === 0) {
            return null;
        }

        // Contradicting pairs: same prefix de 30 chars, same (biz_id,user_id), ambos ativos
        $duplicados = (clone $base)
            ->select(
                'business_id',
                'user_id',
                DB::raw('SUBSTR(fato, 1, 30) as prefix'),
                DB::raw('COUNT(*) as n'),
            )
            ->groupBy('business_id', 'user_id', 'prefix')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $contraditorias = $duplicados->sum('n');

        return round(($contraditorias / $total) * 100, 2);
    }

    /**
     * Lê arquivo de log do dia e extrai durações de gen_ai.span filtradas.
     *
     * Formato: 1 linha por evento, padrão Laravel daily logger:
     *   [YYYY-MM-DD HH:MM:SS] live.INFO: gen_ai.span {"gen_ai.system":"openai",...}
     *
     * @return array<int>
     */
    protected function coletarDuracoesDoLog(?int $businessId, CarbonImmutable $data): array
    {
        $path = storage_path("logs/{$this->logChannel}-{$data->toDateString()}.log");
        if (! file_exists($path)) {
            return [];
        }

        $duracoes = [];
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            return [];
        }

        try {
            while (($line = fgets($fh)) !== false) {
                // Procura JSON após `gen_ai.span ` em cada linha
                $idx = strpos($line, 'gen_ai.span ');
                if ($idx === false) {
                    continue;
                }
                $jsonStr = trim(substr($line, $idx + strlen('gen_ai.span ')));
                $payload = json_decode($jsonStr, true);
                if (! is_array($payload)) {
                    continue;
                }

                if ($businessId !== null && (int) ($payload['gen_ai.business_id'] ?? -1) !== $businessId) {
                    continue;
                }

                $dur = $payload['gen_ai.response.duration_ms'] ?? null;
                if (is_numeric($dur)) {
                    $duracoes[] = (int) $dur;
                }
            }
        } finally {
            fclose($fh);
        }

        return $duracoes;
    }
}
