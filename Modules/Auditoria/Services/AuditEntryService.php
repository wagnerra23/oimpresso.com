<?php

namespace Modules\Auditoria\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * AuditEntryService — listagem/filtragem thin de activity_log.
 *
 * Extrai lógica de listing/filter do AuditoriaController pra reuso e teste
 * isolado. Pareado com Modules/Governance/AuditDrillDownService (Wave H).
 *
 * Tier 0 IRREVOGÁVEL: TODA query scoped por business_id ([ADR 0093]).
 * Whitelist de filtros restrita a colunas INDEXADAS (subject_type/event/causer_kind)
 * — SPEC §Riscos: properties é TEXT, JSON_EXTRACT só em diff individual.
 *
 * NÃO toca RevertService (compliance crítica — whitelist UNREVERTIBLE).
 *
 * Refs: ADR 0127 + ADR 0093 + SPEC US-AUDIT-007/009/010
 */
class AuditEntryService
{
    /**
     * Filtros aceitos (subset whitelist — colunas indexadas).
     */
    private const ALLOWED_FILTERS = ['causer_kind', 'subject_type', 'event'];

    /**
     * Lista paginada multi-tenant scoped.
     *
     * @param  int  $businessId  Tier 0 obrigatório
     * @param  array<string,mixed>  $filters  subset de ALLOWED_FILTERS
     * @param  int|null  $perPage  default config('auditoria.page_size', 50)
     */
    public function list(int $businessId, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $query = $this->baseQuery($businessId);

        foreach (self::ALLOWED_FILTERS as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        return $query
            ->orderByDesc('id')
            ->paginate($perPage ?? (int) config('auditoria.page_size', 50));
    }

    /**
     * Carrega entry individual (raise 404 se não existir / cross-tenant).
     */
    public function find(int $businessId, int $activityId): Activity
    {
        return $this->baseQuery($businessId)
            ->where('id', $activityId)
            ->firstOrFail();
    }

    /**
     * Subset de filtros aceitos (pra Controller passar pro Inertia preserve).
     *
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    public function normalizeFilters(array $raw): array
    {
        return array_intersect_key($raw, array_flip(self::ALLOWED_FILTERS));
    }

    /**
     * Query base com Tier 0 enforcement.
     */
    private function baseQuery(int $businessId): Builder
    {
        return Activity::query()->where('activity_log.business_id', $businessId);
    }
}
