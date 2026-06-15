<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use Illuminate\Support\Carbon;

/**
 * Status de liveness de um host do watcher de ingest (B-LIVE-CHECK, SDD · ADR 0278).
 *
 * DTO tipado (em vez de stdClass) pra ser PHPStan-clean sob Collection invariante e
 * dar ao consumidor (B-SPOF-WA) propriedades tipadas (->status, ->host) sem acesso
 * dinâmico.
 *
 * @see \Modules\TeamMcp\Services\IngestLivenessService
 */
final class IngestLivenessStatus
{
    public function __construct(
        public readonly string $host,
        public readonly ?Carbon $lastIngestAt,
        /** fresh | stale | dead */
        public readonly string $status,
        public readonly ?int $ageMinutes,
    ) {
    }
}
