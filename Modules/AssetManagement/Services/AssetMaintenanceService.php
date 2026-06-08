<?php

namespace Modules\AssetManagement\Services;

use App\Media;
use App\Util\OtelHelper;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\AssetManagement\Entities\AssetMaintenance;
use Modules\AssetManagement\Utils\AssetUtil;

/**
 * Service de manutencao de asset (asset_maintenances).
 *
 * Wave 16 governance D4 Architecture: extraido de AssetMaitenanceController.
 * Wave 25 D9.a: spans OtelHelper::spanBiz em criar/atualizar/remover.
 * Multi-tenant Tier 0 (ADR 0093) — business_id obrigatorio.
 */
class AssetMaintenanceService
{
    public function __construct(
        private Util $commonUtil,
        private AssetUtil $assetUtil,
    ) {
    }

    /**
     * Cria manutencao + dispara notificacao "sent for maintenance".
     * Wave 25 D9.a: span `assetmanagement.maintenance.criar`.
     */
    public function criar(Request $request, int $businessId, int $userId): AssetMaintenance
    {
        return OtelHelper::spanBiz('assetmanagement.maintenance.criar', function () use ($request, $businessId, $userId): AssetMaintenance {
            $input = $request->only('status', 'priority', 'asset_id', 'maintenance_note');

            $ref_count = $this->commonUtil->setAndGetReferenceCount('asset_maintenance', $businessId);
            $asset_settings = $this->assetUtil->getAssetSettings($businessId);

            DB::beginTransaction();

            $prefix = $asset_settings['asset_maintenance_prefix'] ?? null;
            $input['maitenance_id'] = $this->commonUtil->generateReferenceNumber('asset_maintenance', $ref_count, null, $prefix);
            $input['business_id'] = $businessId;
            $input['created_by'] = $userId;

            $maintenance = AssetMaintenance::create($input);

            if ($request->has('attachments')) {
                Media::uploadMedia($businessId, $maintenance, $request, 'attachments');
            }

            $this->assetUtil->sendAssetSentForMaintenanceNotification($maintenance->id);

            DB::commit();

            return $maintenance;
        }, ['business_id' => $businessId, 'user_id' => $userId]);
    }

    /**
     * Atualiza manutencao + dispara notificacao "assigned" se assigned_to mudou.
     * Wave 25 D9.a: span `assetmanagement.maintenance.atualizar`.
     */
    public function atualizar(Request $request, int $id, int $businessId): AssetMaintenance
    {
        return OtelHelper::spanBiz('assetmanagement.maintenance.atualizar', function () use ($request, $id, $businessId): AssetMaintenance {
            $input = $request->only('status', 'priority', 'details', 'assigned_to');

            $maintenance = AssetMaintenance::find($id);
            $previousAssignedTo = $maintenance->assigned_to;

            DB::beginTransaction();

            $maintenance->update($input);

            if ($request->has('attachments')) {
                Media::uploadMedia($businessId, $maintenance, $request, 'attachments');
            }

            if (! empty($input['assigned_to']) && $previousAssignedTo !== $input['assigned_to']) {
                $this->assetUtil->sendAssetAssignedForMaintenanceNotification($maintenance->id);
            }

            DB::commit();

            return $maintenance;
        }, ['business_id' => $businessId, 'maintenance_id' => $id]);
    }

    /**
     * Remove manutencao + media (scopado a business_id).
     * Wave 25 D9.a: span `assetmanagement.maintenance.remover`.
     */
    public function remover(int $id, int $businessId): void
    {
        OtelHelper::spanBiz('assetmanagement.maintenance.remover', function () use ($id, $businessId): void {
            $maintenance = AssetMaintenance::where('business_id', $businessId)->findOrFail($id);
            $maintenance->delete();
            $maintenance->media()->delete();
        }, ['business_id' => $businessId, 'maintenance_id' => $id]);
    }
}
