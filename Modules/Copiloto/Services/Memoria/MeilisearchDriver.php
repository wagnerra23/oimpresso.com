<?php

namespace Modules\Copiloto\Services\Memoria;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Contracts\MemoriaPersistida;
use Modules\Copiloto\Entities\CopilotoMemoriaFato;

/**
 * MeilisearchDriver — driver default da MemoriaContrato (verdade canônica ADR 0036).
 *
 * Self-hosted Meilisearch via Laravel Scout. Custo recorrente R$0/mês —
 * reaproveita binário já instalado em ~/meilisearch no Hostinger e local Windows.
 *
 * Persistência: tabela copiloto_memoria_facts via Eloquent (audit + LGPD via SoftDeletes).
 * Index: Meilisearch via Scout Searchable observers (auto-sync com modelo).
 * Embeddings: gerados pelo Meilisearch via embedder configurado no index
 * (provider OpenAI text-embedding-3-small recomendado; configurar via Meilisearch API).
 *
 * Hybrid search (full-text + semantic) é o default — ratio ajustável via
 * config('copiloto.memoria.meilisearch.semantic_ratio', 0.5).
 *
 * Multi-tenant scope (US-COPI-MEM-005): filtros business_id + user_id em toda query.
 * Temporal validity: valid_until=NULL = ativo; preenchido = superseded.
 *
 * Phase 2 (MEM-MEM-WIRE) — pipeline enhancers (ADR 0054):
 *   1. NegativeCacheService — skip Scout + LLM se query conhecidamente vazia
 *   2. HydeQueryExpander    — expande query em doc hipotético (bridge phrasing gap)
 *   3. LlmReranker          — reordena candidatos por relevância LLM-as-judge
 *
 * Triggers pra upgrade pra Mem0RestDriver (ADR 0036 sprint 8+):
 *   - dedup falha em ≥10% dos casos
 *   - conversas >50 turnos perdem contexto
 *   - conflict resolution temporal precisa de validity windows native
 *   - Wagner pedir explicitamente
 */
class MeilisearchDriver implements MemoriaContrato
{
    public function lembrar(int $businessId, int $userId, string $fato, array $metadata = []): MemoriaPersistida
    {
        $fato_record = CopilotoMemoriaFato::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'fato' => $fato,
            'metadata' => $metadata,
            'valid_from' => now(),
        ]);

        Log::channel('copiloto-ai')->info('MeilisearchDriver::lembrar', [
            'memoria_id' => $fato_record->id,
            'business_id' => $businessId,
            'user_id' => $userId,
        ]);

        return $this->toPersistida($fato_record);
    }

    public function buscar(int $businessId, int $userId, string $query, int $topK = 5): array
    {
        // MEM-HOT-1 (ADR 0047): Scout default = só full-text. Pra ativar hybrid
        // (full-text + semantic via embedder OpenAI configurado no índice), passamos
        // callback que sobrescreve os search params do MeilisearchEngine::performSearch.
        //
        // MEM-MEM-WIRE Phase 2 (ADR 0054): 3 enhancers opcionais em sequência:
        //   1. NegativeCache — skip tudo se query conhecidamente vazia (TTL 5min)
        //   2. HyDE          — expande query em doc hipotético → melhor vector match
        //   3. Reranker      — reordena candidatos via LLM-as-judge pós-retrieval

        // 1. Negative cache — retorna [] imediatamente se query recentemente vazia
        /** @var NegativeCacheService $negCache */
        $negCache = app(NegativeCacheService::class);
        if ($negCache->ehNegativo($businessId, $userId, $query)) {
            return [];
        }

        $embedder      = (string) config('copiloto.memoria.meilisearch.embedder', 'openai');
        $semanticRatio = (float)  config('copiloto.memoria.meilisearch.semantic_ratio', 0.7);

        // 2. HyDE expansion — retorna [query] ou [query, doc_hipotetico]
        /** @var HydeQueryExpander $hyde */
        $hyde    = app(HydeQueryExpander::class);
        $queries = $hyde->expandir($query);

        // Quando reranker está ativo, buscamos 2× candidatos pra ele ter material
        $fetchK = config('copiloto.reranker.enabled', false) ? $topK * 2 : $topK;

        // 3. Scout hybrid search — uma iteração por query (original + HyDE se ativo)
        $resultSets = [];
        foreach ($queries as $searchQuery) {
            $callback = function ($index, string $q, array $params) use ($businessId, $userId, $fetchK, $embedder, $semanticRatio) {
                $params['hybrid'] = [
                    'embedder'      => $embedder,
                    'semanticRatio' => $semanticRatio,
                ];
                $params['filter'] = sprintf(
                    'business_id = %d AND user_id = %d',
                    $businessId,
                    $userId
                );
                $params['limit'] = $fetchK;

                return $index->search($q, $params);
            };

            $hits = CopilotoMemoriaFato::search($searchQuery, $callback)
                ->take($fetchK)
                ->get()
                ->filter(fn (CopilotoMemoriaFato $f) => $f->valid_until === null);

            $resultSets[] = $hits;
        }

        // RRF merge quando HyDE produziu 2 result sets
        $merged = $this->rrfMerge($resultSets, $fetchK);

        // Marca negativo se zero resultados pra evitar overhead futuro
        if ($merged->isEmpty()) {
            $negCache->marcarNegativo($businessId, $userId, $query);

            Log::channel('copiloto-ai')->debug('MeilisearchDriver::buscar', [
                'business_id' => $businessId,
                'user_id'     => $userId,
                'query_chars' => strlen($query),
                'top_k'       => $topK,
                'hits'        => 0,
                'hyde_queries' => count($queries),
            ]);

            return [];
        }

        // 4. LLM Reranker — reordena candidatos por relevância à query original
        /** @var LlmReranker $reranker */
        $reranker   = app(LlmReranker::class);
        $candidatos = $merged->map(fn (CopilotoMemoriaFato $f) => [
            'id'      => $f->id,
            'snippet' => mb_substr($f->fato, 0, 300),
            'score'   => 1.0,
        ])->values()->all();

        $reranked = $reranker->reranquear($query, $candidatos, $topK);

        // Mapeia ids reranqueados de volta pra models
        $idMap = $merged->keyBy('id');
        $final = collect($reranked)
            ->map(fn (array $c) => $idMap->get($c['id']))
            ->filter()
            ->values();

        Log::channel('copiloto-ai')->debug('MeilisearchDriver::buscar', [
            'business_id'    => $businessId,
            'user_id'        => $userId,
            'query_chars'    => strlen($query),
            'top_k'          => $topK,
            'embedder'       => $embedder,
            'semantic_ratio' => $semanticRatio,
            'hyde_queries'   => count($queries),
            'candidates'     => $merged->count(),
            'hits'           => $final->count(),
        ]);

        return $final->map(fn (CopilotoMemoriaFato $f) => $this->toPersistida($f))->all();
    }

    /**
     * Reciprocal Rank Fusion — merge de múltiplos result sets em ranking único.
     * k=60 é o valor canônico da literatura (Cormack 2009).
     *
     * @param array<int, Collection> $resultSets
     */
    private function rrfMerge(array $resultSets, int $topK): Collection
    {
        if (count($resultSets) === 1) {
            return $resultSets[0];
        }

        $scores = [];
        $models = [];

        foreach ($resultSets as $results) {
            foreach ($results as $rank => $model) {
                $id           = $model->id;
                $models[$id]  = $model;
                $scores[$id]  = ($scores[$id] ?? 0.0) + 1.0 / (60 + $rank + 1);
            }
        }

        arsort($scores);
        $topIds = array_slice(array_keys($scores), 0, $topK);

        return collect(array_map(fn ($id) => $models[$id], $topIds));
    }

    public function atualizar(int $memoriaId, string $novoFato, array $metadata = []): void
    {
        $antigo = CopilotoMemoriaFato::find($memoriaId);
        if ($antigo === null) {
            return;
        }

        // Supersedes: marca antigo com valid_until + cria novo (append-only)
        $antigo->update(['valid_until' => now()]);

        CopilotoMemoriaFato::create([
            'business_id' => $antigo->business_id,
            'user_id' => $antigo->user_id,
            'fato' => $novoFato,
            'metadata' => $metadata,
            'valid_from' => now(),
        ]);

        Log::channel('copiloto-ai')->info('MeilisearchDriver::atualizar', [
            'memoria_id_antigo' => $memoriaId,
            'business_id' => $antigo->business_id,
        ]);
    }

    public function esquecer(int $memoriaId): void
    {
        $fato = CopilotoMemoriaFato::find($memoriaId);
        if ($fato === null) {
            return;
        }

        // SoftDelete = LGPD opt-out. Observer do Scout remove do index automaticamente.
        $fato->delete();

        Log::channel('copiloto-ai')->info('MeilisearchDriver::esquecer', [
            'memoria_id' => $memoriaId,
            'business_id' => $fato->business_id,
        ]);
    }

    public function listar(int $businessId, int $userId): array
    {
        return CopilotoMemoriaFato::doUser($businessId, $userId)
            ->ativos()
            ->orderByDesc('valid_from')
            ->get()
            ->map(fn (CopilotoMemoriaFato $f) => $this->toPersistida($f))
            ->all();
    }

    private function toPersistida(CopilotoMemoriaFato $f): MemoriaPersistida
    {
        return new MemoriaPersistida(
            id: $f->id,
            businessId: $f->business_id,
            userId: $f->user_id,
            fato: $f->fato,
            metadata: $f->metadata ?? [],
            validFrom: $f->valid_from?->toIso8601String(),
            validUntil: $f->valid_until?->toIso8601String(),
        );
    }
}
