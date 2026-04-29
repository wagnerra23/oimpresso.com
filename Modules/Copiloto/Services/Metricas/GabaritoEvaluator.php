<?php

namespace Modules\Copiloto\Services\Metricas;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Entities\MemoriaGabarito;
use Modules\Copiloto\Entities\Mensagem;

/**
 * MEM-EVAL-1 (ADR 0049+0050) — Roda gabarito de perguntas Larissa-style e
 * calcula Recall@K, Precision@K, MRR, plus matches de resposta_esperada.
 *
 * Operação:
 *   1. Pra cada pergunta no gabarito (filtro por business):
 *      a. Cria conversa efêmera (business_id correto pra cross-tenant test)
 *      b. Chama MemoriaContrato::buscar() com a pergunta — captura top-K snippets
 *      c. Calcula recall@3, precision@3, MRR contra memoria_esperada_keys
 *      d. (Opcional) chama AiAdapter::responderChat — captura resposta full
 *      e. Verifica resposta_esperada_pattern (regex match)
 *   2. Agrega: média dos scores por categoria + global
 *   3. Retorna array estruturado pronto pra logar/dashboard
 *
 * NÃO grava nada no DB — caller decide (via comando).
 *
 * Custo por run completo (50 perguntas × 1 LLM call): ~$0,02 USD = R$ 0,11.
 */
class GabaritoEvaluator
{
    public function __construct(
        protected MemoriaContrato $memoria,
        protected AiAdapter $ai,
    ) {
    }

    /**
     * Roda gabarito completo pra um business (ou null = todas).
     *
     * @param array{include_resposta?: bool, top_k?: int} $opts
     * @return array<string, mixed> Métricas agregadas + por-categoria + por-pergunta
     */
    public function rodar(?int $businessId, array $opts = []): array
    {
        $includeResposta = (bool) ($opts['include_resposta'] ?? false);
        $topK = (int) ($opts['top_k'] ?? 10);

        $perguntas = MemoriaGabarito::ativo()
            ->doBusiness($businessId)
            ->orderBy('id')
            ->get();

        if ($perguntas->isEmpty()) {
            return [
                'business_id' => $businessId,
                'total_perguntas' => 0,
                'erro' => 'Sem perguntas no gabarito pra esse business',
            ];
        }

        $resultados = [];
        foreach ($perguntas as $p) {
            $resultados[] = $this->avaliarUma($p, $businessId, $topK, $includeResposta);
        }

        return [
            'business_id'     => $businessId,
            'total_perguntas' => count($resultados),
            'agregado'        => $this->agregar($resultados),
            'por_categoria'   => $this->agruparPorCategoria($resultados),
            'detalhes'        => $resultados,
            'rodado_em'       => now()->toIso8601String(),
        ];
    }

    /**
     * Avalia 1 pergunta isolada.
     */
    protected function avaliarUma(
        MemoriaGabarito $p,
        ?int $businessId,
        int $topK,
        bool $includeResposta
    ): array {
        $startedAt = microtime(true);

        // 1. Recall direto via MemoriaContrato
        try {
            $bizParaBusca = $p->business_id ?? $businessId ?? 0;
            $userParaBusca = 0; // gabarito não amarrado a user específico
            $hits = $this->memoria->buscar(
                businessId: $bizParaBusca,
                userId: $userParaBusca,
                query: $p->pergunta,
                limit: $topK,
            );
        } catch (\Throwable $e) {
            return [
                'id_pergunta'     => $p->id,
                'categoria'       => $p->categoria,
                'pergunta'        => $p->pergunta,
                'erro'            => 'recall: ' . $e->getMessage(),
                'recall@3'        => 0,
                'precision@3'     => 0,
                'mrr'             => 0,
                'duration_ms'     => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        }

        // Hits podem ser objetos ou arrays — normalizar pra strings
        $snippets = collect($hits)->map(function ($h) {
            if (is_string($h)) return $h;
            if (is_object($h)) return $h->fato ?? $h->snippet ?? $h->content ?? json_encode($h);
            if (is_array($h)) return $h['fato'] ?? $h['snippet'] ?? $h['content'] ?? json_encode($h);
            return (string) $h;
        })->values()->all();

        $top3 = array_slice($snippets, 0, 3);

        // 2. Calcula métricas baseado em memoria_esperada_keys
        $recallAt3 = $p->coverageScore($top3);
        $recallAt10 = $p->coverageScore($snippets);
        $precisionAt3 = $this->precisionAtK($top3, $p);
        $mrr = $this->mrr($snippets, $p);

        $r = [
            'id_pergunta'     => $p->id,
            'categoria'       => $p->categoria,
            'subcategoria'    => $p->subcategoria,
            'pergunta'        => $p->pergunta,
            'recall@3'        => round($recallAt3, 3),
            'recall@10'       => round($recallAt10, 3),
            'precision@3'     => round($precisionAt3, 3),
            'mrr'             => round($mrr, 3),
            'top_snippet'     => mb_substr($top3[0] ?? '', 0, 100),
            'snippets_count'  => count($snippets),
        ];

        // 3. (Opcional) gera resposta full + checa pattern
        if ($includeResposta) {
            try {
                // Conversa efêmera — não persiste
                $conv = new Conversa([
                    'business_id' => $p->business_id ?? $businessId,
                    'user_id'     => 0,
                    'titulo'      => 'eval-gabarito-' . $p->id,
                    'status'      => 'ativa',
                ]);
                // Não salva — só usa em memória pro responderChat

                $resposta = $this->ai->responderChat($conv, $p->pergunta);
                $r['resposta'] = mb_substr($resposta, 0, 300);
                if ($p->resposta_esperada_pattern) {
                    $r['resposta_match'] = (bool) @preg_match(
                        '/' . str_replace('/', '\/', $p->resposta_esperada_pattern) . '/iu',
                        $resposta,
                    );
                }
            } catch (\Throwable $e) {
                $r['resposta_erro'] = $e->getMessage();
            }
        }

        $r['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        return $r;
    }

    /**
     * Precision@K: dos top-K, quantos contêm pelo menos 1 esperado_key.
     */
    protected function precisionAtK(array $topK, MemoriaGabarito $p): float
    {
        if (empty($topK)) return 0.0;
        $keys = $p->memoria_esperada_keys ?? [];
        if (empty($keys)) return 0.0;

        $relevantes = 0;
        foreach ($topK as $snippet) {
            $snippetLower = mb_strtolower((string) $snippet);
            foreach ($keys as $key) {
                if (mb_stripos($snippetLower, mb_strtolower($key)) !== false) {
                    $relevantes++;
                    break;
                }
            }
        }
        return $relevantes / count($topK);
    }

    /**
     * Mean Reciprocal Rank: 1/rank do PRIMEIRO snippet relevante (0 se nenhum).
     */
    protected function mrr(array $snippets, MemoriaGabarito $p): float
    {
        $keys = $p->memoria_esperada_keys ?? [];
        if (empty($keys)) return 0.0;

        foreach ($snippets as $i => $snippet) {
            $snippetLower = mb_strtolower((string) $snippet);
            foreach ($keys as $key) {
                if (mb_stripos($snippetLower, mb_strtolower($key)) !== false) {
                    return 1 / ($i + 1);
                }
            }
        }
        return 0.0;
    }

    /**
     * Agrega scores médios across todas perguntas.
     */
    protected function agregar(array $resultados): array
    {
        $coll = collect($resultados);
        return [
            'recall@3_avg'     => round($coll->avg('recall@3') ?? 0, 3),
            'recall@10_avg'    => round($coll->avg('recall@10') ?? 0, 3),
            'precision@3_avg'  => round($coll->avg('precision@3') ?? 0, 3),
            'mrr_avg'          => round($coll->avg('mrr') ?? 0, 3),
            'duration_ms_avg'  => (int) round($coll->avg('duration_ms') ?? 0),
            'duration_ms_p95'  => $this->p($coll->pluck('duration_ms')->all(), 0.95),
            'resposta_match_pct' => $coll->has('resposta_match')
                ? round(($coll->where('resposta_match', true)->count() / max(1, $coll->where('resposta_match', '!=', null)->count())) * 100, 1)
                : null,
        ];
    }

    protected function agruparPorCategoria(array $resultados): array
    {
        return collect($resultados)
            ->groupBy('categoria')
            ->map(fn (Collection $g) => [
                'count'          => $g->count(),
                'recall@3_avg'   => round($g->avg('recall@3') ?? 0, 3),
                'recall@10_avg'  => round($g->avg('recall@10') ?? 0, 3),
                'precision@3_avg' => round($g->avg('precision@3') ?? 0, 3),
                'mrr_avg'        => round($g->avg('mrr') ?? 0, 3),
            ])
            ->toArray();
    }

    /**
     * Percentil simples — array de números ordenado.
     */
    protected function p(array $values, float $pct): int
    {
        if (empty($values)) return 0;
        sort($values);
        $idx = (int) floor(count($values) * $pct);
        return (int) ($values[min($idx, count($values) - 1)] ?? 0);
    }
}
