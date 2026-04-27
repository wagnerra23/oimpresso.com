<?php

namespace Modules\Copiloto\Services\Memoria;

use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Contracts\MemoriaPersistida;

/**
 * NullMemoriaDriver — driver pra dev, dry_run e CI.
 *
 * Não chama rede. Mantém estado em array em memória da request (não persiste entre requests).
 * Útil pra testar fluxos do Copiloto sem custo de embedding nem dependência de Meilisearch.
 *
 * Ver ADR 0036.
 */
class NullMemoriaDriver implements MemoriaContrato
{
    private array $store = [];
    private int $nextId = 1;

    public function lembrar(int $businessId, int $userId, string $fato, array $metadata = []): MemoriaPersistida
    {
        $memoria = new MemoriaPersistida(
            id: $this->nextId++,
            businessId: $businessId,
            userId: $userId,
            fato: $fato,
            metadata: $metadata,
            validFrom: now()->toIso8601String(),
        );

        $this->store[$memoria->id] = $memoria;

        return $memoria;
    }

    public function buscar(int $businessId, int $userId, string $query, int $topK = 5): array
    {
        return collect($this->store)
            ->filter(fn (MemoriaPersistida $m) => $m->businessId === $businessId && $m->userId === $userId)
            ->filter(fn (MemoriaPersistida $m) => $m->validUntil === null)
            ->take($topK)
            ->values()
            ->all();
    }

    public function atualizar(int $memoriaId, string $novoFato, array $metadata = []): void
    {
        $antigo = $this->store[$memoriaId] ?? null;
        if ($antigo === null) {
            return;
        }

        $this->store[$memoriaId] = new MemoriaPersistida(
            id: $antigo->id,
            businessId: $antigo->businessId,
            userId: $antigo->userId,
            fato: $antigo->fato,
            metadata: $antigo->metadata,
            validFrom: $antigo->validFrom,
            validUntil: now()->toIso8601String(),
        );

        $novo = new MemoriaPersistida(
            id: $this->nextId++,
            businessId: $antigo->businessId,
            userId: $antigo->userId,
            fato: $novoFato,
            metadata: $metadata,
            validFrom: now()->toIso8601String(),
        );

        $this->store[$novo->id] = $novo;
    }

    public function esquecer(int $memoriaId): void
    {
        unset($this->store[$memoriaId]);
    }

    public function listar(int $businessId, int $userId): array
    {
        return collect($this->store)
            ->filter(fn (MemoriaPersistida $m) => $m->businessId === $businessId && $m->userId === $userId)
            ->filter(fn (MemoriaPersistida $m) => $m->validUntil === null)
            ->values()
            ->all();
    }
}
