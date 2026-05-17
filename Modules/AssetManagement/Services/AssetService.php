<?php

namespace Modules\AssetManagement\Services;

use App\Media;
use App\Util\OtelHelper;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetWarranty;
use Modules\AssetManagement\Utils\AssetUtil;

/**
 * Service de Asset (criar / atualizar / remover) com garantias e media.
 *
 * Wave 16 governance D4 Architecture: extracao de AssetController.
 * Wave 25 D9.a: spans OtelHelper::spanBiz em criar/atualizar/remover
 *               (latência observável + business_id Tier 0 propagado).
 * Multi-tenant Tier 0 (ADR 0093): business_id obrigatorio em todo metodo
 * de persistencia. Service NAO acessa session — caller passa business_id.
 */
class AssetService
{
    public function __construct(
        private Util $commonUtil,
        private AssetUtil $assetUtil,
    ) {
    }

    /**
     * Cria asset + media + garantias em transacao.
     *
     * Wave 25 D9.a: span `asset_management.criar` (latência DB transaction + media upload).
     */
    public function criar(Request $request, int $businessId, int $userId): Asset
    {
        return OtelHelper::spanBiz('assetmanagement.asset.criar', function () use ($request, $businessId, $userId): Asset {
            $input = $request->only(
                'asset_code', 'name', 'quantity', 'model', 'serial_no', 'category_id',
                'location_id', 'purchase_date', 'unit_price', 'depreciation',
                'is_allocatable', 'description', 'purchase_type'
            );

            $input['business_id'] = $businessId;
            $input['created_by'] = $userId;
            $input['is_allocatable'] = ! empty($input['is_allocatable']) ? 1 : 0;

            DB::beginTransaction();

            if (empty($input['asset_code'])) {
                $ref_count = $this->commonUtil->setAndGetReferenceCount('asset_code', $businessId);
                $asset_settings = $this->assetUtil->getAssetSettings($businessId);
                $prefix = $asset_settings['asset_code_prefix'] ?? null;
                $input['asset_code'] = $this->commonUtil->generateReferenceNumber('asset_code', $ref_count, null, $prefix);
            }

            $input = $this->normalizarCampos($input);

            $asset = Asset::create($input);

            if ($request->has('image')) {
                Media::uploadMedia($businessId, $asset, $request, 'image', true);
            }

            $warranties = $this->montarGarantias($request);
            if (! empty($warranties)) {
                $asset->warranties()->createMany($warranties);
            }

            DB::commit();

            return $asset;
        }, [
            'business_id' => $businessId,
            'user_id'     => $userId,
        ]);
    }

    /**
     * Atualiza asset existente (scopado a business_id).
     *
     * Wave 25 D9.a: span `asset_management.atualizar`.
     */
    public function atualizar(Request $request, int $id, int $businessId): Asset
    {
        return OtelHelper::spanBiz('assetmanagement.asset.atualizar', function () use ($request, $id, $businessId): Asset {
            $input = $request->only(
                'name', 'quantity', 'model', 'category_id', 'location_id',
                'purchase_date', 'unit_price', 'depreciation', 'is_allocatable',
                'description', 'purchase_type', 'serial_no'
            );
            $input['is_allocatable'] = ! empty($input['is_allocatable']) ? 1 : 0;

            DB::beginTransaction();

            $input = $this->normalizarCampos($input);

            $asset = Asset::where('business_id', $businessId)->findOrFail($id);
            $asset->update($input);

            // Garantias existentes (edicao)
            $edited_warranty_ids = [];
            if (! empty($request->input('edit_warranty'))) {
                foreach ($request->input('edit_warranty') as $key => $value) {
                    $edited_warranty_ids[] = $key;
                    $start_date = $this->commonUtil->uf_date($value['start_date']);
                    AssetWarranty::where('id', $key)->update([
                        'start_date' => $start_date,
                        'end_date' => \Carbon::parse($start_date)->addMonths($value['months'])->format('Y-m-d'),
                        'additional_cost' => $this->commonUtil->num_uf($value['additional_cost']),
                        'additional_note' => $value['additional_note'],
                    ]);
                }
            }
            AssetWarranty::where('asset_id', $asset->id)
                ->whereNotIn('id', $edited_warranty_ids)
                ->delete();

            $warranties = $this->montarGarantias($request);
            if (! empty($warranties)) {
                $asset->warranties()->createMany($warranties);
            }

            if ($request->has('image')) {
                Media::uploadMedia($businessId, $asset, $request, 'image', true);
            }

            DB::commit();

            return $asset;
        }, [
            'business_id' => $businessId,
            'asset_id'    => $id,
        ]);
    }

    /**
     * Remove asset + media (scopado a business_id).
     *
     * Wave 25 D9.a: span `asset_management.remover`.
     */
    public function remover(int $id, int $businessId): void
    {
        OtelHelper::spanBiz('assetmanagement.asset.remover', function () use ($id, $businessId): void {
            $asset = Asset::where('business_id', $businessId)->findOrFail($id);
            $asset->delete();
            $asset->media()->delete();
        }, [
            'business_id' => $businessId,
            'asset_id'    => $id,
        ]);
    }

    /**
     * Normalizacao de campos numericos/data — extraido pra reuso.
     */
    private function normalizarCampos(array $input): array
    {
        if (! empty($input['quantity'])) {
            $input['quantity'] = $this->commonUtil->num_uf($input['quantity']);
        }
        if (! empty($input['purchase_date'])) {
            $input['purchase_date'] = $this->commonUtil->uf_date($input['purchase_date']);
        }
        if (! empty($input['unit_price'])) {
            $input['unit_price'] = $this->commonUtil->num_uf($input['unit_price']);
        }
        if (! empty($input['depreciation'])) {
            $input['depreciation'] = $this->commonUtil->num_uf($input['depreciation']);
        }
        return $input;
    }

    /**
     * Constroi array de garantias a partir de payload (start_dates + months).
     */
    private function montarGarantias(Request $request): array
    {
        $warranties = [];
        if (! empty($request->input('start_dates'))) {
            $months = $request->input('months');
            foreach ($request->input('start_dates') as $key => $value) {
                if (! empty($value) && ! empty($months[$key])) {
                    $start_date = $this->commonUtil->uf_date($value);
                    $warranties[] = [
                        'start_date' => $start_date,
                        'end_date' => \Carbon::parse($start_date)->addMonths($months[$key])->format('Y-m-d'),
                        'additional_cost' => $this->commonUtil->num_uf($request->input('additional_cost')[$key]),
                        'additional_note' => $request->input('additional_note')[$key],
                    ];
                }
            }
        }
        return $warranties;
    }
}
