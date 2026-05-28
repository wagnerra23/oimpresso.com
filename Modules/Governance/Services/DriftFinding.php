<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

/**
 * DTO Finding individual produzido por DriftChecker::check().
 *
 * Persistido em mcp_alertas_eventos via trait PersistsDriftAlert.
 *
 * Convenções:
 * - target = identificador único do recurso afetado (path, ADR id, package name, channel name)
 * - target_type = categoria pra filtrar/agrupar ('file', 'adr', 'package', 'channel', 'model')
 * - evidence = JSON-serializable, vai parar em mcp_alertas_eventos.metadata
 * - business_id = null pra drift repo-wide (default), preencher se checker per-business
 *
 * ADR 0216 §DriftFinding
 */
final readonly class DriftFinding
{
    /**
     * @param array<string, mixed> $evidence
     */
    public function __construct(
        public string $target,
        public string $target_type,
        public string $severity,
        public string $message,
        public array $evidence = [],
        public ?int $business_id = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target,
            'target_type' => $this->target_type,
            'severity' => $this->severity,
            'message' => $this->message,
            'evidence' => $this->evidence,
            'business_id' => $this->business_id,
        ];
    }
}
