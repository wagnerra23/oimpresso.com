<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Builder;
use Modules\Crm\Entities\CrmCallLog;

/**
 * CallLogService — thin de query/aggregation de CrmCallLog.
 *
 * Service extraído de `CallLogController::index/show` (Wave 18 D4.c). Concentra
 * a builder paginável + filtros aceitos numa única fonte (Controller +
 * DataTables + futura UI Inertia compartilham mesmo Service).
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093): caller passa `$businessId` resolvido. Service
 * NUNCA toca session — back-pressure pra Job assíncrono (relatório CRM noturno
 * pode reusar `baseQuery()` direto sem replicar lógica).
 *
 * Métricas OTel `crm.call_log.*` — `App\Util\OtelHelper` canônico.
 *
 * @see Modules\Crm\Http\Controllers\CallLogController
 * @see Modules\Crm\Entities\CrmCallLog
 */
class CallLogService
{
    /**
     * Filtros aceitos (whitelist — proteção SQL injection + drift).
     */
    private const ALLOWED_FILTERS = ['contact_id', 'user_id', 'start_time', 'end_time'];

    /**
     * Builder base scoped por business + joins canônicos.
     *
     * Replica EXATAMENTE o que `CallLogController::index` faz hoje pra evitar
     * regressão DataTables. Future-proof: subir build de SELECT pro Service.
     */
    public function baseQuery(int $businessId): Builder
    {
        return OtelHelper::spanBiz('crm.call_log.base_query', function () use ($businessId) {
            return CrmCallLog::query()
                ->where('crm_call_logs.business_id', $businessId)
                ->leftJoin('contacts as c', 'crm_call_logs.contact_id', '=', 'c.id')
                ->leftJoin('users as u', 'crm_call_logs.user_id', '=', 'u.id')
                ->leftJoin('users as created_users', 'crm_call_logs.created_by', '=', 'created_users.id');
        }, ['business_id' => $businessId]);
    }

    /**
     * Aplica filtros aceitos sobre uma query base.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        $whitelist = array_intersect_key($filters, array_flip(self::ALLOWED_FILTERS));

        if (! empty($whitelist['contact_id'])) {
            $query->where('crm_call_logs.contact_id', $whitelist['contact_id']);
        }

        if (! empty($whitelist['user_id'])) {
            $query->where('crm_call_logs.created_by', $whitelist['user_id']);
        }

        if (! empty($whitelist['start_time']) && ! empty($whitelist['end_time'])) {
            $query->whereDate('crm_call_logs.start_time', '>=', $whitelist['start_time'])
                ->whereDate('crm_call_logs.start_time', '<=', $whitelist['end_time']);
        }

        return $query;
    }

    /**
     * Restringe query a logs criados pelo próprio user (permission `view_own`).
     */
    public function restrictToOwner(Builder $query, int $userId): Builder
    {
        return $query->where('crm_call_logs.created_by', $userId);
    }

    /**
     * Total agregado de duração em segundos pra business + range opcional.
     *
     * Útil pra dashboards CRM (KPI tempo total em chamadas).
     */
    public function totalDurationSeconds(int $businessId, array $filters = []): int
    {
        return OtelHelper::spanBiz('crm.call_log.total_duration', function () use ($businessId, $filters) {
            $query = CrmCallLog::query()->where('business_id', $businessId);
            if (! empty($filters['start_time']) && ! empty($filters['end_time'])) {
                $query->whereDate('start_time', '>=', $filters['start_time'])
                    ->whereDate('start_time', '<=', $filters['end_time']);
            }

            return (int) $query->sum('duration');
        }, ['business_id' => $businessId]);
    }
}
