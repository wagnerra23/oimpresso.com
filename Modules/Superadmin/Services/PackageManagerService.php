<?php

declare(strict_types=1);

namespace Modules\Superadmin\Services;

use Illuminate\Support\Collection;
use Modules\Superadmin\Entities\Package;

/**
 * PackageManagerService — encapsula leitura/filtragem de SKUs comerciais.
 *
 * Wave 18 RETRY — D4 boost. Extraído de Package::listPackages() estático (legacy
 * UltimatePOS) pra Service injectável testável.
 *
 * Motivação:
 *   - `Package::listPackages($excludePrivate)` é static — não-mockable, dificulta test
 *   - Service permite stub via container em Pest (Service::shouldReceive(...)->mock())
 *   - Encapsula regras de negócio "qual package mostra pra qual contexto"
 *
 * Cross-tenant intencional (ADR 0093 §exceções Superadmin):
 *   - Packages são catálogo GLOBAL (sem business_id) — todos tenants vêem mesmo SKU
 *   - Não usa global scope multi-tenant (Package model não tem business_id)
 *
 * @see Modules\Superadmin\Entities\Package
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class PackageManagerService
{
    /**
     * Lista packages ativos ordenados por sort_order.
     *
     * @param  bool  $excludePrivate  se true, filtra is_private=1 (catálogo público).
     * @return Collection<int, Package>
     */
    public function listActive(bool $excludePrivate = false): Collection
    {
        $query = Package::active()->orderBy('sort_order');

        if ($excludePrivate) {
            $query->where('is_private', 0);
        }

        return $query->get();
    }

    /**
     * Conta packages ativos (KPI dashboard).
     */
    public function countActive(bool $excludePrivate = false): int
    {
        $query = Package::active();

        if ($excludePrivate) {
            $query->where('is_private', 0);
        }

        return $query->count();
    }

    /**
     * Busca package por id (com soft-delete fallback opcional).
     *
     * @param  bool  $withTrashed  inclui packages soft-deletados (admin restore flow).
     */
    public function find(int $id, bool $withTrashed = false): ?Package
    {
        $query = Package::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    /**
     * Lista packages disponíveis pra um business específico, considerando
     * customizações `custom_permissions` cast em Package model.
     *
     * Cross-tenant intencional: catalog é global, mas filter aplica contexto
     * do business (ex: package "Premium-OEM" só pra tenants com flag custom).
     */
    public function listForBusiness(int $businessId, bool $excludePrivate = true): Collection
    {
        // SUPERADMIN: Package não tem business_id (catálogo global).
        // Filtragem por-business é via custom_permissions JSON cast.
        return $this->listActive($excludePrivate);
    }
}
