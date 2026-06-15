<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\McpIngestHeartbeat;

/**
 * IngestLivenessService — reader de liveness do ingest (B-LIVE-CHECK, SDD · ADR 0278).
 *
 * Lê `mcp_ingest_heartbeat` (escrito por B-LIVE-HB / #2791 — CcIngestController) e
 * classifica cada host (máquina/cwd do watcher local) em fresh/stale/dead pela idade
 * do `last_ingest_at`. É a metade-leitora que faltava pra detectar o SPOF do watcher
 * (nenhum ingest recente = pipeline cego). O consumidor (B-SPOF-WA: whats-active deixa
 * de dar "tudo livre" falso quando o pipeline está morto) é tarefa SEPARADA.
 *
 * SoC (Princípio 5, espelha {@see \Modules\Jana\Services\WorkLease\WorkLeaseService}):
 * a JANELA de frescor vive AQUI (runtime now()), NUNCA no schema (coluna gerada não
 * pode usar now()). Degrada gracioso se a tabela ainda não migrou — e enviesa pra
 * INCERTEZA (host desconhecido = 'dead'), nunca pra falso "tudo bem" (blind ≠ safe).
 *
 * Tier 0 ({@see ADR 0093}/{@see ADR 0280}): heartbeat é sinal de infra/máquina, SEM
 * business_id (cross-tenant by design — Grupo A). Este reader não filtra por tenant.
 *
 * @see \Modules\TeamMcp\Entities\McpIngestHeartbeat
 * @see \Modules\TeamMcp\Http\Controllers\Mcp\CcIngestController (writer, B-LIVE-HB)
 */
class IngestLivenessService
{
    /** Ingeriu nos últimos FRESH_MINUTES → watcher vivo. */
    public const FRESH_MINUTES = 15;

    /** Sem ingest há mais de STALE_MINUTES (ou nunca) → watcher morto (SPOF). */
    public const STALE_MINUTES = 60;

    private const TABLE = 'mcp_ingest_heartbeat';

    /**
     * Classifica uma marca de último ingest em fresh/stale/dead.
     * null (host que nunca ingeriu) → 'dead' (enviesa pra incerteza, blind ≠ safe).
     */
    public function classify(?Carbon $lastIngestAt): string
    {
        if ($lastIngestAt === null) {
            return 'dead';
        }

        $now = now();

        if ($lastIngestAt->greaterThanOrEqualTo($now->copy()->subMinutes(self::FRESH_MINUTES))) {
            return 'fresh';
        }

        if ($lastIngestAt->greaterThan($now->copy()->subMinutes(self::STALE_MINUTES))) {
            return 'stale';
        }

        return 'dead';
    }

    /**
     * Status de um host específico. Tabela ausente OU host desconhecido → 'dead'
     * (incerteza, não falso-OK).
     */
    public function classifyHost(string $host): string
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 'dead';
        }

        $hb = McpIngestHeartbeat::query()->where('host', $host)->first();

        return $hb ? $this->classify($hb->last_ingest_at) : 'dead';
    }

    /**
     * Todos os hosts com status + idade (array tipado por host). Degrada gracioso
     * (lista vazia) se a tabela ainda não migrou — nunca estoura.
     *
     * Retorna `array` (não Collection) de propósito: o generic de Collection::map é
     * invariante no Larastan e rejeita até shapes idênticos; `list<array{...}>` é
     * PHPStan-clean sem essa fricção.
     *
     * @return list<array{host: string, last_ingest_at: \Illuminate\Support\Carbon|null, status: string, age_minutes: int|null}>
     */
    public function all(): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        $rows = [];

        foreach (McpIngestHeartbeat::query()->get() as $hb) {
            $last = $hb->last_ingest_at;

            $rows[] = [
                'host'           => $hb->host,
                'last_ingest_at' => $last,
                'status'         => $this->classify($last),
                'age_minutes'    => $last ? (int) $last->diffInMinutes(now()) : null,
            ];
        }

        return $rows;
    }

    /**
     * Contagem por status. Tabela ausente → tudo zero (sem hosts conhecidos = cego).
     *
     * @return array{fresh: int, stale: int, dead: int}
     */
    public function summary(): array
    {
        $all = $this->all();

        return [
            'fresh' => count(array_filter($all, static fn (array $r): bool => $r['status'] === 'fresh')),
            'stale' => count(array_filter($all, static fn (array $r): bool => $r['status'] === 'stale')),
            'dead'  => count(array_filter($all, static fn (array $r): bool => $r['status'] === 'dead')),
        ];
    }
}
