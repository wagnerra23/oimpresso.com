<?php

namespace Modules\Jana\Services\Memoria;

use App\Util\OtelHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Contracts\MemoriaContrato;
use Modules\Jana\Contracts\MemoriaPersistida;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Services\Retrieval\Reranker;

/**
 * MeilisearchDriver — driver default da MemoriaContrato (verdade canônica ADR 0036).
 *
 * Self-hosted Meilisearch via Laravel Scout. Custo recorrente R$ [redacted Tier 0]/mês —
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
        $fato_record = MemoriaFato::create([
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
        // D9.a Observability — span zero-cost wrapper sobre todo o pipeline hybrid.
        return OtelHelper::spanBiz('jana.memoria.buscar', function () use ($businessId, $userId, $query, $topK) {
            return $this->buscarInterno($businessId, $userId, $query, $topK);
        }, [
            'business_id' => $businessId,
            'user_id' => $userId,
            'query_chars' => strlen($query),
            'top_k' => $topK,
        ]);
    }

    /**
     * Implementação interna isolada de buscar() — preservada idêntica
     * pra OtelHelper::spanBiz envolver sem afetar comportamento.
     */
    private function buscarInterno(int $businessId, int $userId, string $query, int $topK = 5): array
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

            $hits = MemoriaFato::search($searchQuery, $callback)
                ->take($fetchK)
                ->get()
                ->filter(fn (MemoriaFato $f) => $f->valid_until === null);

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

        // 4. Reranker canônico via contrato Reranker (GAP-A — AUDITORIA 2026-05-13 §5 G3)
        //    Driver resolvido por config('copiloto.reranker.driver'): rrf|llm|null
        //    - rrf  (default): RrfReranker — zero custo, ~1ms latência
        //    - llm:           LlmRerankerAdapter wrapping legacy LlmReranker (gpt-4o-mini)
        //    - null:          NullReranker passthrough (feature flag off)
        /** @var Reranker $reranker */
        $reranker   = app(Reranker::class);

        // 3.5 Time-decay (K1 — Onda 5 dossier 2026-05-13). Aplica half-life decay
        // pós-recall, pré-rerank. Doc velho (lifecycle=historical/superseded) cai;
        // doc recente accepted sobe. Score base fica 1.0 (sem hybrid score do Scout
        // exposto), então time-decay opera como pure multiplier sobre o material já
        // filtered (ordem preservada pelo $merged). Reranker depois reordena por
        // score ajustado.
        $candidatos = $this->applyTimeDecay($merged);

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

        return $final->map(fn (MemoriaFato $f) => $this->toPersistida($f))->all();
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
        $antigo = MemoriaFato::find($memoriaId);
        if ($antigo === null) {
            return;
        }

        // Supersedes: marca antigo com valid_until + cria novo (append-only)
        $antigo->update(['valid_until' => now()]);

        MemoriaFato::create([
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
        $fato = MemoriaFato::find($memoriaId);
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
        return MemoriaFato::doUser($businessId, $userId)
            ->ativos()
            ->orderByDesc('valid_from')
            ->get()
            ->map(fn (MemoriaFato $f) => $this->toPersistida($f))
            ->all();
    }

    /**
     * Aplica half-life time-decay + status multipliers (K1 — Onda 5 dossier 2026-05-13).
     *
     * Fórmula canônica (TDS Temporal Layer 2026):
     *   score_final = score_base × (
     *       (1 - temporal_weight) + temporal_weight × 0.5^(age_days / half_life)
     *   ) × status_multiplier
     *
     * Defaults (Wagner aprovou 2026-05-13):
     *   - temporal_weight = 0.4 (40% time, 60% meaning)
     *   - half_life per doc_type: adr=365, spec=180, session=30, handoff=14
     *   - status: accepted=1.2, proposed=1.0, historical=0.5, superseded=0.3
     *
     * Edge cases:
     *   - JANA_TIME_DECAY_ENABLED=false → score base preservado (back-compat)
     *   - doc sem published_at/valid_from/created_at → temporal factor = 1.0 (sem decay)
     *   - doc_type/status ausente em metadata → defaults da config
     *   - half_life inválido (<=0) → fallback default
     *
     * @param  Collection<int, MemoriaFato> $hits Collection mapped por buscar() — ordem do RRF preservada.
     * @return array<int, array{id:int, snippet:string, score:float}> Formato esperado pelo Reranker.
     */
    private function applyTimeDecay(Collection $hits): array
    {
        $enabled = (bool) config('copiloto.time_decay.enabled', true);

        // Bypass quando flag off (back-compat) — score base 1.0 idêntico ao legado.
        if (! $enabled) {
            return $hits->map(fn (MemoriaFato $f) => [
                'id'      => $f->id,
                'snippet' => mb_substr($f->fato, 0, 300),
                'score'   => 1.0,
            ])->values()->all();
        }

        $temporalWeight    = (float) config('copiloto.time_decay.temporal_weight', 0.4);
        $halfLifeMap       = (array) config('copiloto.time_decay.half_life', []);
        $statusMultipliers = (array) config('copiloto.time_decay.status_multipliers', []);

        $defaultHalfLife        = (int)   ($halfLifeMap['default'] ?? 180);
        $defaultStatusMultiplier = (float) ($statusMultipliers['default'] ?? 1.0);

        $now = now();

        return $hits->map(function (MemoriaFato $f) use (
            $temporalWeight,
            $halfLifeMap,
            $statusMultipliers,
            $defaultHalfLife,
            $defaultStatusMultiplier,
            $now,
        ) {
            $metadata = $f->metadata ?? [];
            $docType  = strtolower((string) ($metadata['doc_type'] ?? 'default'));
            $status   = strtolower((string) ($metadata['status']   ?? 'default'));

            // Half-life em dias por tipo (fallback default se chave ausente).
            $halfLifeDays = (int) ($halfLifeMap[$docType] ?? $defaultHalfLife);
            if ($halfLifeDays <= 0) {
                $halfLifeDays = $defaultHalfLife > 0 ? $defaultHalfLife : 180;
            }

            // Status multiplier (default 1.0 se status desconhecido).
            $statusMultiplier = (float) ($statusMultipliers[$status] ?? $defaultStatusMultiplier);

            // Resolve data de referência — prioridade: metadata.published_at,
            // valid_from, created_at. Sem nenhuma → fator temporal = 1.0 (no decay).
            $publishedAt = $this->resolveDocDate($f);
            if ($publishedAt === null) {
                $temporalFactor = 1.0;
            } else {
                $ageDays        = max(0.0, $publishedAt->diffInDays($now, false));
                $decay          = pow(0.5, $ageDays / $halfLifeDays);
                $temporalFactor = (1.0 - $temporalWeight) + $temporalWeight * $decay;
            }

            // Score base 1.0 (Scout hybrid não expõe score raw via Eloquent —
            // ordem do RRF é o sinal preservado). Reranker depois reordena.
            $scoreBase  = 1.0;
            $scoreFinal = $scoreBase * $temporalFactor * $statusMultiplier;

            return [
                'id'      => $f->id,
                'snippet' => mb_substr($f->fato, 0, 300),
                'score'   => $scoreFinal,
            ];
        })->values()->all();
    }

    /**
     * Resolve melhor data disponível pra cálculo de age_days.
     * Ordem: metadata.published_at → valid_from → created_at → null.
     */
    private function resolveDocDate(MemoriaFato $f): ?\Illuminate\Support\Carbon
    {
        $metadata = $f->metadata ?? [];

        // 1. metadata.published_at (campo canônico ADR memory/decisions)
        if (! empty($metadata['published_at'])) {
            try {
                return \Illuminate\Support\Carbon::parse((string) $metadata['published_at']);
            } catch (\Throwable $e) {
                // Data malformada — cai pro próximo fallback.
            }
        }

        // 2. valid_from do próprio fato
        if ($f->valid_from !== null) {
            return $f->valid_from;
        }

        // 3. created_at (sempre populado pelo Eloquent)
        if ($f->created_at !== null) {
            return $f->created_at;
        }

        return null;
    }

    private function toPersistida(MemoriaFato $f): MemoriaPersistida
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
