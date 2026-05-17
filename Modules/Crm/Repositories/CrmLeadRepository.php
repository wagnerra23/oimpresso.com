<?php

declare(strict_types=1);

namespace Modules\Crm\Repositories;

use App\Util\OtelHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Crm\Entities\CrmContact;

/**
 * CrmLeadRepository — repository pattern thin sobre `CrmContact` (Leads).
 *
 * Wave 18 D4.d: introduz primeiro Repository no Crm. Concentra queries de
 * domínio (`leads`, `customers`, `lookup`) numa camada que pode ser mockada
 * em tests (vs hitting MySQL real). Padrão Anthropic 2026 trend report:
 * Repository = thin adapter sobre Eloquent, NÃO Active Record substituto.
 *
 * Regra dura: Repository EXIGE `$businessId` em TODOS os métodos públicos
 * (multi-tenant Tier 0 IRREVOGÁVEL — ADR 0093). Nunca usar
 * `withoutGlobalScopes()` aqui.
 *
 * Métricas OTel `crm.lead_repo.*` via `App\Util\OtelHelper` canônico.
 *
 * @see Modules\Crm\Entities\CrmContact
 * @see Modules\Crm\Services\LeadAssignmentService
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class CrmLeadRepository
{
    /**
     * Builder base pra leads de um business (`contacts.type = lead`).
     */
    public function baseQuery(int $businessId): Builder
    {
        return CrmContact::query()
            ->where('contacts.business_id', $businessId)
            ->where('contacts.type', 'lead');
    }

    /**
     * Find por id já scopado (firstOrFail levanta 404 se cross-tenant).
     */
    public function findOrFail(int $businessId, int $leadId): CrmContact
    {
        return OtelHelper::spanBiz('crm.lead_repo.find', function () use ($businessId, $leadId) {
            return $this->baseQuery($businessId)
                ->where('contacts.id', $leadId)
                ->firstOrFail();
        }, ['business_id' => $businessId, 'lead_id' => $leadId]);
    }

    /**
     * Listing paginado de Leads pra UI Inertia / DataTables.
     */
    public function paginate(int $businessId, int $perPage = 25): LengthAwarePaginator
    {
        return OtelHelper::spanBiz('crm.lead_repo.paginate', function () use ($businessId, $perPage) {
            return $this->baseQuery($businessId)
                ->orderByDesc('contacts.created_at')
                ->paginate($perPage);
        }, ['business_id' => $businessId, 'per_page' => $perPage]);
    }

    /**
     * Conta leads por life_stage pra KPI dashboard.
     *
     * @return array<string, int>
     */
    public function countByLifeStage(int $businessId): array
    {
        return OtelHelper::spanBiz('crm.lead_repo.count_life_stage', function () use ($businessId) {
            return $this->baseQuery($businessId)
                ->selectRaw('crm_life_stage, COUNT(*) as total')
                ->groupBy('crm_life_stage')
                ->pluck('total', 'crm_life_stage')
                ->toArray();
        }, ['business_id' => $businessId]);
    }

    /**
     * Conta leads por source pra KPI dashboard.
     *
     * @return array<string, int>
     */
    public function countBySource(int $businessId): array
    {
        return OtelHelper::spanBiz('crm.lead_repo.count_source', function () use ($businessId) {
            return $this->baseQuery($businessId)
                ->selectRaw('crm_source, COUNT(*) as total')
                ->groupBy('crm_source')
                ->pluck('total', 'crm_source')
                ->toArray();
        }, ['business_id' => $businessId]);
    }

    /**
     * Total absoluto de leads no business.
     */
    public function count(int $businessId): int
    {
        return OtelHelper::spanBiz('crm.lead_repo.count', function () use ($businessId) {
            return $this->baseQuery($businessId)->count();
        }, ['business_id' => $businessId]);
    }
}
