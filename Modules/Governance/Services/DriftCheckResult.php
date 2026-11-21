<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

/**
 * DTO resultado agregado de DriftChecker::check().
 *
 * - ok = true quando drift_count = 0 (semântica exit code 0 do orchestrator)
 * - drift_count = sizeof(findings)
 * - duration_ms = wall-clock do scan (input pra OtelHelper::span + dashboard)
 * - centrifugo_payload = override opcional do checker (default = sintetizado pelo orchestrator)
 * - measured = false quando o checker NÃO CONSEGUIU medir (binário ausente, JSON inválido,
 *   tabela/config inexistente). Nesse caso ok = false e drift_count = 0: não é verde nem drift.
 *   Antes deste estado, 9 dos 13 checkers devolviam clean() no erro — verde sem ter medido
 *   (§5 proibicoes 2026-07-29, LC-33). Use notMeasured() com o motivo.
 *
 * ADR 0216 §DriftCheckResult
 */
final readonly class DriftCheckResult
{
    /**
     * @param array<int, DriftFinding> $findings
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $centrifugo_payload
     */
    public function __construct(
        public string $name,
        public bool $ok,
        public int $drift_count,
        public array $findings,
        public array $metadata = [],
        public int $duration_ms = 0,
        public ?array $centrifugo_payload = null,
        public bool $measured = true,
        public ?string $motivo_nao_medido = null,
    ) {
    }

    /**
     * Não consegui medir. Nunca verde (ok=false), nunca drift (sem findings).
     *
     * @param array<string, mixed> $metadata
     */
    public static function notMeasured(string $name, string $motivo, int $duration_ms = 0, array $metadata = []): self
    {
        return new self(
            name: $name,
            ok: false,
            drift_count: 0,
            findings: [],
            metadata: $metadata,
            duration_ms: $duration_ms,
            measured: false,
            motivo_nao_medido: $motivo,
        );
    }

    /** 'clean' | 'drifted' | 'nao_medido' */
    public function status(): string
    {
        if (! $this->measured) {
            return 'nao_medido';
        }

        return $this->ok ? 'clean' : 'drifted';
    }

    public static function clean(string $name, int $duration_ms = 0, array $metadata = []): self
    {
        return new self(
            name: $name,
            ok: true,
            drift_count: 0,
            findings: [],
            metadata: $metadata,
            duration_ms: $duration_ms,
        );
    }

    /**
     * @param array<int, DriftFinding> $findings
     * @param array<string, mixed> $metadata
     */
    public static function drifted(
        string $name,
        array $findings,
        int $duration_ms = 0,
        array $metadata = [],
        ?array $centrifugo_payload = null,
    ): self {
        return new self(
            name: $name,
            ok: false,
            drift_count: count($findings),
            findings: $findings,
            metadata: $metadata,
            duration_ms: $duration_ms,
            centrifugo_payload: $centrifugo_payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'ok' => $this->ok,
            'status' => $this->status(),
            'motivo_nao_medido' => $this->motivo_nao_medido,
            'drift_count' => $this->drift_count,
            'duration_ms' => $this->duration_ms,
            'findings' => array_map(static fn (DriftFinding $f) => $f->toArray(), $this->findings),
            'metadata' => $this->metadata,
        ];
    }
}
