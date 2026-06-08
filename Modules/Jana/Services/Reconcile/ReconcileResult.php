<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Reconcile;

/**
 * DTO agregado de Reconciler::reconcile(). Espelha DriftCheckResult (ADR 0216).
 *
 * - inSync = true quando driftCount = 0 (semântica exit code 0 do orquestrador).
 * - healedCount = quantos drifts foram curados nesta run (heal=true + healable).
 * - durationMs = wall-clock (input pra OtelHelper::span + dashboard).
 *
 * ADR 0237.
 */
final readonly class ReconcileResult
{
    /**
     * @param array<int, ReconcileDrift> $drifts
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $name,
        public bool $inSync,
        public int $driftCount,
        public int $healedCount,
        public array $drifts,
        public int $durationMs = 0,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function synced(string $name, int $durationMs = 0, array $metadata = []): self
    {
        return new self(
            name: $name,
            inSync: true,
            driftCount: 0,
            healedCount: 0,
            drifts: [],
            durationMs: $durationMs,
            metadata: $metadata,
        );
    }

    /**
     * @param array<int, ReconcileDrift> $drifts
     * @param array<string, mixed> $metadata
     */
    public static function from(string $name, array $drifts, int $durationMs = 0, array $metadata = []): self
    {
        $healed = count(array_filter($drifts, static fn (ReconcileDrift $d) => $d->healed));

        return new self(
            name: $name,
            inSync: $drifts === [],
            driftCount: count($drifts),
            healedCount: $healed,
            drifts: $drifts,
            durationMs: $durationMs,
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'in_sync' => $this->inSync,
            'drift_count' => $this->driftCount,
            'healed_count' => $this->healedCount,
            'duration_ms' => $this->durationMs,
            'drifts' => array_map(static fn (ReconcileDrift $d) => $d->toArray(), $this->drifts),
            'metadata' => $this->metadata,
        ];
    }
}
