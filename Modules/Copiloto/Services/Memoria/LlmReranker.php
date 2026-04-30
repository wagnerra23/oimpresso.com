<?php

namespace Modules\Copiloto\Services\Memoria;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

/**
 * MEM-S10-2 (ADR 0037 Sprint 10) — LLM-as-Reranker.
 *
 * Pega top-K candidatos do retrieval BM25/vector/RRF (geralmente 10-20),
 * pede pra LLM-cheap (gpt-4o-mini) ranquear por relevância à query.
 *
 * Substitui cross-encoder dedicated (que precisaria GPU + container Python).
 * Trade-off: ~200ms latência extra vs +5pp recall@5 (literatura RAG 2026).
 *
 * Cache: 5min TTL (rerank de mesma query+candidatos).
 *
 * Custo: ~150 tokens out × $0.60/M = $0.00009 por rerank = R$ 0,0005.
 * Em 100 reranks/dia: R$ 0,05/dia. Justifica se ganho de qualidade > 10%.
 */
class LlmReranker
{
    /**
     * Reordena candidatos por relevância à query.
     * Retorna candidatos na nova ordem (mantém todos ou filtra abaixo de threshold).
     *
     * @param array<int, array{id: int|string, snippet: string, score?: float}> $candidatos
     * @return array Mesmo formato, reordenado, com novo `score_rerank` adicionado.
     */
    public function reranquear(string $query, array $candidatos, int $topK = 5): array
    {
        if (! config('copiloto.reranker.enabled', false) || empty($candidatos)) {
            return array_slice($candidatos, 0, $topK);
        }

        // Cache: query + ids dos candidatos (mesma combinação, mesmo resultado por 5min)
        $ids = collect($candidatos)->pluck('id')->sort()->values()->all();
        $cacheKey = 'copiloto:rerank:' . hash('sha256', $query . '|' . implode(',', $ids));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return array_slice($cached, 0, $topK);
        }

        try {
            // Monta lista numerada pro LLM
            $listaSnippets = collect($candidatos)
                ->map(fn ($c, $i) => "[" . ($i + 1) . "] " . mb_substr($c['snippet'] ?? '', 0, 200))
                ->implode("\n\n");

            $agent = new AnonymousAgent(
                instructions: $this->systemPrompt(),
                messages: [],
                tools: [],
            );

            $response = $agent->prompt(
                "Pergunta:\n{$query}\n\nCandidatos:\n{$listaSnippets}\n\n" .
                "Devolva um JSON array com os índices ordenados do MAIS relevante pro MENOS, " .
                "incluindo só os realmente relevantes (≥3 de relevância em escala 1-5)."
            );

            $rawJson = (string) $response;

            // Parse: extrai array de números
            preg_match('/\[[\d,\s]+\]/', $rawJson, $m);
            $ordemFinal = json_decode($m[0] ?? '[]', true) ?: [];

            // Aplica ordem ao array original (1-indexed → 0-indexed)
            $reordenados = [];
            foreach ($ordemFinal as $rank => $idx) {
                $i = ((int) $idx) - 1;
                if (isset($candidatos[$i])) {
                    $c = $candidatos[$i];
                    $c['score_rerank'] = round(1 / ($rank + 1), 3);
                    $reordenados[] = $c;
                }
            }

            // Se rerank devolveu menos que topK, adiciona resto na ordem original
            if (count($reordenados) < $topK) {
                foreach ($candidatos as $i => $c) {
                    if (! collect($reordenados)->pluck('id')->contains($c['id'] ?? null)) {
                        $reordenados[] = array_merge($c, ['score_rerank' => 0]);
                        if (count($reordenados) >= $topK) break;
                    }
                }
            }

            Cache::put($cacheKey, $reordenados, now()->addMinutes(5));

            Log::channel('copiloto-ai')->info('LlmReranker: reranked', [
                'query' => mb_substr($query, 0, 80),
                'candidatos_count' => count($candidatos),
                'reranked_count' => count($reordenados),
                'top_k_returned' => min($topK, count($reordenados)),
            ]);

            return array_slice($reordenados, 0, $topK);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('LlmReranker: falhou (degradação): ' . $e->getMessage());
            return array_slice($candidatos, 0, $topK);
        }
    }

    protected function systemPrompt(): string
    {
        return <<<PROMPT
        Você é um reranker para sistema RAG.

        Receba uma pergunta + lista de candidatos numerados, e devolva os índices
        em ORDEM de relevância à pergunta (do mais relevante pro menos), filtrando
        candidatos com relevância < 3 (escala 1-5).

        FORMATO de saída — APENAS:
        [3, 1, 5, 2]

        SEM markdown, SEM explicação, SEM JSON wrapper. SÓ o array.

        REGRAS:
        - Avalia se snippet RESPONDE diretamente a pergunta
        - Snippets factuais (números, datas) > snippets vagos
        - Snippets sobre o BUSINESS específico do user > snippets genéricos
        - Se NENHUM é relevante, devolve array vazio: []
        PROMPT;
    }
}
