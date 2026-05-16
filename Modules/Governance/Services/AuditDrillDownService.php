<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AuditDrillDownService — leitura especializada de `mcp_audit_log` (Constituição Art. 9).
 *
 * Encapsula queries de drill-down sobre o log append-only (trigger MySQL — ADR 0084).
 * Service thin: zero side-effect, apenas filtra/agrega para UI de Governance.
 *
 * Filtros suportados (todos opcionais):
 *   - period: 1h | 24h | 7d | 30d  (default 24h)
 *   - actor: slug em `mcp_actors.slug` (resolve user_id via join)
 *   - endpoint: enum fixo (7 valores em mcp_audit_log.endpoint)
 *   - status: 'ok' | 'error' | etc
 *
 * @see Modules\Governance\Http\Controllers\AuditController
 */
class AuditDrillDownService
{
    /**
     * Retorna entries recentes do audit log aplicando filtros opcionais.
     *
     * @param  int                                                                    $limit   Máximo de linhas (default 50, máx prático 200)
     * @param  array{period?: string, actor?: ?string, endpoint?: ?string, status?: ?string}|null  $filters
     * @return Collection<int, object>
     */
    public function getRecentEntries(int $limit = 50, ?array $filters = null): Collection
    {
        $filters = $filters ?? [];
        $period   = $filters['period']   ?? '24h';
        $actor    = $filters['actor']    ?? null;
        $endpoint = $filters['endpoint'] ?? null;
        $status   = $filters['status']   ?? null;

        $cutoff = $this->cutoffFor($period);

        // Schema mcp_audit_log: `ts` é o timestamp canonical da tabela (auto current_timestamp).
        $q = DB::table('mcp_audit_log')->where('ts', '>', $cutoff);

        if ($actor !== null && $actor !== '') {
            // mcp_audit_log usa user_id; resolve slug -> user_id via mcp_actors.
            $userIds = DB::table('mcp_actors')
                ->where('slug', $actor)
                ->whereNull('revoked_at')
                ->pluck('user_id')
                ->filter()
                ->values()
                ->all();

            if (! empty($userIds)) {
                $q->whereIn('user_id', $userIds);
            } else {
                $q->whereRaw('1=0'); // actor não encontrado = zero results (fail-safe)
            }
        }
        if ($endpoint !== null && $endpoint !== '') {
            $q->where('endpoint', $endpoint);
        }
        if ($status !== null && $status !== '') {
            $q->where('status', $status);
        }

        return $q->orderByDesc('ts')
            ->limit($limit)
            ->select('id', 'user_id', 'business_id', 'endpoint', 'tool_or_resource', 'status', 'duration_ms', 'ts as created_at')
            ->get();
    }

    /**
     * KPIs agregados (total/errors/unique_users) a partir de uma coleção de entries.
     *
     * @param  Collection<int, object>  $entries
     * @return array{total: int, errors: int, unique_users: int}
     */
    public function kpisFor(Collection $entries): array
    {
        return [
            'total'        => $entries->count(),
            'errors'       => $entries->where('status', '!=', 'ok')->count(),
            'unique_users' => $entries->pluck('user_id')->unique()->count(),
        ];
    }

    /**
     * Lista distinct endpoints presentes nos últimos 30d (pra dropdown de filtro).
     *
     * @return array<int, string>
     */
    public function availableEndpoints(): array
    {
        return DB::table('mcp_audit_log')
            ->where('ts', '>', now()->subDays(30))
            ->select('endpoint')
            ->distinct()
            ->orderBy('endpoint')
            ->limit(50)
            ->pluck('endpoint')
            ->all();
    }

    /**
     * Lista actors ativos (slug + display_name) — para dropdown de filtro.
     *
     * @return Collection<int, object>
     */
    public function availableActors(): Collection
    {
        return DB::table('mcp_actors')
            ->whereNull('revoked_at')
            ->select('slug', 'display_name')
            ->orderBy('slug')
            ->get();
    }

    /**
     * Calcula cutoff Carbon a partir do enum de período.
     */
    private function cutoffFor(string $period): Carbon
    {
        return match ($period) {
            '1h'    => now()->subHour(),
            '24h'   => now()->subDay(),
            '7d'    => now()->subDays(7),
            '30d'   => now()->subDays(30),
            default => now()->subDay(),
        };
    }
}
