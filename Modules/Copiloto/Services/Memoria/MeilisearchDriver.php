<?php

namespace Modules\Copiloto\Services\Memoria;

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
        // Scout search com filtros multi-tenant + scope ativos
        $results = CopilotoMemoriaFato::search($query)
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->take($topK)
            ->get();

        return $results
            ->filter(fn (CopilotoMemoriaFato $f) => $f->valid_until === null)
            ->map(fn (CopilotoMemoriaFato $f) => $this->toPersistida($f))
            ->values()
            ->all();
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
