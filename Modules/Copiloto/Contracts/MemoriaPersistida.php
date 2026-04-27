<?php

namespace Modules\Copiloto\Contracts;

/**
 * MemoriaPersistida — DTO de retorno dos métodos da MemoriaContrato.
 *
 * Não é Eloquent — driver-agnostic. MeilisearchDriver hidrata de CopilotoMemoriaFato,
 * Mem0RestDriver hidrataria de payload da API, NullMemoriaDriver de fixtures.
 */
final class MemoriaPersistida
{
    public function __construct(
        public readonly int $id,
        public readonly int $businessId,
        public readonly int $userId,
        public readonly string $fato,
        public readonly array $metadata = [],
        public readonly ?string $validFrom = null,
        public readonly ?string $validUntil = null,
        public readonly ?float $score = null, // similarity score quando vier de buscar()
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->businessId,
            'user_id' => $this->userId,
            'fato' => $this->fato,
            'metadata' => $this->metadata,
            'valid_from' => $this->validFrom,
            'valid_until' => $this->validUntil,
            'score' => $this->score,
        ];
    }
}
