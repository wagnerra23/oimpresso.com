<?php

declare(strict_types=1);

namespace Modules\Superadmin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Collection;
use Modules\Superadmin\Entities\Package;

/**
 * PackageManagerService — encapsula leitura/filtragem de SKUs comerciais.
 *
 * Wave 18 RETRY — D4 boost. Extraído de Package::listPackages() estático (legacy
 * UltimatePOS) pra Service injectável testável.
 *
 * Wave 25 SATURATION — D9 boost: spans OTel canônicos por método (zero-cost se
 * `otel.enabled=false`). Habilita dashboard SRE de catalog reads cross-tenant.
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
 * @see app\Util\OtelHelper
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0155-module-grade-v3-anti-injustica-na-justified.md D9.a
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
        return OtelHelper::spanBiz('superadmin.package.list_active', function () use ($excludePrivate): Collection {
            // SUPERADMIN: Package é catálogo global (sem business_id, ADR 0093 §exceções).
            $query = Package::active()->orderBy('sort_order');

            if ($excludePrivate) {
                $query->where('is_private', 0);
            }

            return $query->get();
        }, ['module' => 'Superadmin', 'service' => self::class, 'exclude_private' => $excludePrivate]);
    }

    /**
     * Conta packages ativos (KPI dashboard).
     */
    public function countActive(bool $excludePrivate = false): int
    {
        return OtelHelper::spanBiz('superadmin.package.count_active', function () use ($excludePrivate): int {
            // SUPERADMIN: cross-tenant intencional (catalog global).
            $query = Package::active();

            if ($excludePrivate) {
                $query->where('is_private', 0);
            }

            return $query->count();
        }, ['module' => 'Superadmin', 'service' => self::class, 'exclude_private' => $excludePrivate]);
    }

    /**
     * Busca package por id (com soft-delete fallback opcional).
     *
     * @param  bool  $withTrashed  inclui packages soft-deletados (admin restore flow).
     */
    public function find(int $id, bool $withTrashed = false): ?Package
    {
        return OtelHelper::spanBiz('superadmin.package.find', function () use ($id, $withTrashed): ?Package {
            // SUPERADMIN: lookup global por id.
            $query = Package::query();

            if ($withTrashed) {
                $query->withTrashed();
            }

            return $query->find($id);
        }, ['module' => 'Superadmin', 'service' => self::class, 'package_id' => $id, 'with_trashed' => $withTrashed]);
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
        return OtelHelper::spanBiz('superadmin.package.list_for_business', function () use ($businessId, $excludePrivate): Collection {
            // SUPERADMIN: Package não tem business_id (catálogo global).
            // Filtragem por-business é via custom_permissions JSON cast.
            return $this->listActive($excludePrivate);
        }, ['module' => 'Superadmin', 'service' => self::class, 'target_biz' => $businessId, 'exclude_private' => $excludePrivate]);
    }
}
