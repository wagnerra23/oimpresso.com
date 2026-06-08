<?php

namespace Modules\Woocommerce\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Woocommerce\Entities\WoocommerceSyncLog;

/**
 * WoocommerceSyncLogRepository — query layer dos logs de sync.
 *
 * Antes (D4 baixo): Controller `viewSyncLog()` montava query Datatables
 * inline com leftJoin + select + DB::raw + 130+ linhas de transformações.
 *
 * Depois: Repository encapsula queries; Controller só chama método nomeado
 * com `business_id` + `sync_types` permitidos.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): TODA query parte de `where('business_id', X)`
 * — nunca trazemos logs cross-tenant. Pattern validado em
 * `Tests/Feature/MultiTenantIsolationTest.php`.
 *
 * Filtro `sync_types` reflete RBAC: usuário sem `woocommerce.sync_orders` NÃO
 * vê linhas com sync_type='orders' — mantemos esse contrato aqui.
 */
class WoocommerceSyncLogRepository
{
    /**
     * Query Datatables — base com leftJoin users + select consolidado.
     *
     * @param  array<int, string>  $syncTypesPermitidos  ['categories', 'all_products', 'new_products', 'orders']
     * @param  bool  $superadmin  Se true, NÃO aplica filtro sync_types (vê tudo do biz)
     */
    public function paraDatatable(int $businessId, array $syncTypesPermitidos, bool $superadmin = false): Builder
    {
        $query = WoocommerceSyncLog::where('woocommerce_sync_logs.business_id', $businessId)
            ->leftJoin('users as U', 'U.id', '=', 'woocommerce_sync_logs.created_by')
            ->select([
                'woocommerce_sync_logs.created_at',
                'sync_type',
                'operation_type',
                DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"),
                'woocommerce_sync_logs.data',
                'woocommerce_sync_logs.details as log_details',
                'woocommerce_sync_logs.id as DT_RowId',
            ]);

        if (! $superadmin && ! empty($syncTypesPermitidos)) {
            $query->whereIn('sync_type', $syncTypesPermitidos);
        }

        return $query;
    }

    /**
     * Busca detalhes de um log individual — scope obrigatório `business_id`.
     */
    public function detalhe(int $businessId, int $id): ?WoocommerceSyncLog
    {
        return WoocommerceSyncLog::where('business_id', $businessId)
            ->find($id);
    }
}
