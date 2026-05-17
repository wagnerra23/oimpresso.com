<?php

declare(strict_types=1);

namespace Modules\Crm\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Crm\Entities\CrmContact;

/**
 * Contract Wave 23 D2 — reuso explícito CrmLeadRepository.
 *
 * Permite outros módulos (Sells, Manufacturing, Jana auto-import) consumirem
 * lookups de Lead sem acoplamento direto na classe concreta. Container
 * registra `CrmLeadRepositoryInterface::class => CrmLeadRepository::class` em
 * `Modules/Crm/Providers/CrmServiceProvider.php`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): TODOS os métodos exigem
 * `$businessId` explícito. Implementação NUNCA usa `withoutGlobalScopes`.
 *
 * @see Modules\Crm\Repositories\CrmLeadRepository (implementação canônica)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
interface CrmLeadRepositoryInterface
{
    public function baseQuery(int $businessId): Builder;

    public function findOrFail(int $businessId, int $leadId): CrmContact;

    public function paginate(int $businessId, int $perPage = 25): LengthAwarePaginator;

    /**
     * @return array<string, int>
     */
    public function countByLifeStage(int $businessId): array;

    /**
     * @return array<string, int>
     */
    public function countBySource(int $businessId): array;

    public function count(int $businessId): int;
}
