<?php

namespace Modules\AssetManagement\Services;

use App\Util\OtelHelper;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetTransaction;
use Modules\AssetManagement\Utils\AssetUtil;

/**
 * Service de alocacao de asset (asset_transactions transaction_type=allocate).
 *
 * Wave 16 governance D4 Architecture: extraido de AssetAllocationController.
 * Wave 25 D9.a: spans OtelHelper::spanBiz em criar/atualizar/remover.
 * Multi-tenant Tier 0 (ADR 0093) — business_id obrigatorio.
 */
class AssetAllocationService
{
    public function __construct(
        private Util $commonUtil,
        private AssetUtil $assetUtil,
    ) {
    }

    /**
     * Cria alocacao em transacao. Wave 25 D9.a: span `assetmanagement.allocation.criar`.
     */
    public function criar(Request $request, int $businessId, int $userId): AssetTransaction
    {
        return OtelHelper::spanBiz('assetmanagement.allocation.criar', function () use ($request, $businessId, $userId): AssetTransaction {
            $input = $request->only(
                'ref_no', 'asset_id', 'quantity', 'receiver',
                'transaction_datetime', 'reason', 'allocated_upto'
            );
            $input['transaction_type'] = 'allocate';
            $input['business_id'] = $businessId;
            $input['created_by'] = $userId;

            DB::beginTransaction();

            if (empty($input['ref_no'])) {
                $ref_count = $this->commonUtil->setAndGetReferenceCount('allocation_code', $businessId);
                $asset_settings = $this->assetUtil->getAssetSettings($businessId);
                $prefix = $asset_settings['allocation_code_prefix'] ?? null;
                $input['ref_no'] = $this->commonUtil->generateReferenceNumber('allocation_code', $ref_count, null, $prefix);
            }

            $input = $this->normalizarCampos($input);

            $trans = AssetTransaction::create($input);

            DB::commit();

            return $trans;
        }, ['business_id' => $businessId, 'user_id' => $userId]);
    }

    /**
     * Atualiza alocacao existente (scopado a business_id).
     * Wave 25 D9.a: span `assetmanagement.allocation.atualizar`.
     */
    public function atualizar(Request $request, int $id, int $businessId): AssetTransaction
    {
        return OtelHelper::spanBiz('assetmanagement.allocation.atualizar', function () use ($request, $id, $businessId): AssetTransaction {
            $input = $request->only(
                'asset_id', 'quantity', 'receiver',
                'transaction_datetime', 'reason', 'allocated_upto'
            );

            DB::beginTransaction();

            $input = $this->normalizarCampos($input);

            $trans = AssetTransaction::where('business_id', $businessId)->findOrFail($id);
            $trans->update($input);

            DB::commit();

            return $trans;
        }, ['business_id' => $businessId, 'allocation_id' => $id]);
    }

    /**
     * Remove alocacao (scopado a business_id).
     * Wave 25 D9.a: span `assetmanagement.allocation.remover`.
     */
    public function remover(int $id, int $businessId): void
    {
        OtelHelper::spanBiz('assetmanagement.allocation.remover', function () use ($id, $businessId): void {
            $trans = AssetTransaction::where('business_id', $businessId)->findOrFail($id);
            $trans->delete();
        }, ['business_id' => $businessId, 'allocation_id' => $id]);
    }

    /**
     * Calcula qty disponivel de um asset alocado (used by edit form).
     */
    public function quantidadeDisponivel(AssetTransaction $allocated): int
    {
        $asset = Asset::leftJoin('asset_transactions as AT', function ($join) {
            $join->on('assets.id', '=', 'AT.asset_id')
                ->where('transaction_type', 'allocate');
        })
            ->where('assets.business_id', $allocated->business_id)
            ->where('assets.id', $allocated->asset_id)
            ->select(
                'assets.id as id',
                DB::raw('SUM(COALESCE(AT.quantity, 0)) as allocated_qty'),
                DB::raw('(SELECT SUM(COALESCE(AR.quantity, 0)) FROM asset_transactions AS AR WHERE(AR.asset_id=assets.id AND AR.transaction_type=\'revoke\')) as revoked_qty')
            )
            ->first();

        return (int) ($asset->allocated_qty - $asset->revoked_qty);
    }

    /**
     * Normaliza campos numericos/data.
     */
    private function normalizarCampos(array $input): array
    {
        if (! empty($input['transaction_datetime'])) {
            $input['transaction_datetime'] = $this->commonUtil->uf_date($input['transaction_datetime'], true);
        }
        if (! empty($input['allocated_upto'])) {
            $input['allocated_upto'] = $this->commonUtil->uf_date($input['allocated_upto']);
        }
        if (! empty($input['quantity'])) {
            $input['quantity'] = $this->commonUtil->num_uf($input['quantity']);
        }
        return $input;
    }
}
