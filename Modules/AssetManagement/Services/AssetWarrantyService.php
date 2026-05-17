<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Log;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetWarranty;

/**
 * D9 Observability — Wave 18 saturação AssetManagement.
 *
 * Service de garantias com OtelHelper spans + log estruturado por chamada.
 * Extraido de AssetService (que ja tinha logica inline complexa) pra dar
 * observability granular sobre criacao/revogacao de garantia.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): business_id obrigatorio.
 *
 * @see Modules\AssetManagement\Entities\AssetWarranty
 * @see Modules\AssetManagement\Services\AssetService::montarGarantias
 */
class AssetWarrantyService
{
    /**
     * Adiciona garantia a um asset existente. Span OtelHelper + log estruturado.
     */
    public function adicionar(int $assetId, int $businessId, array $payload): AssetWarranty
    {
        return OtelHelper::spanBiz('assetmanagement.warranty.add', function () use ($assetId, $businessId, $payload) {
            $asset = Asset::where('business_id', $businessId)->findOrFail($assetId);

            $warranty = $asset->warranties()->create([
                'start_date'      => $payload['start_date'],
                'end_date'        => $payload['end_date'],
                'additional_cost' => $payload['additional_cost'] ?? 0,
                'additional_note' => $payload['additional_note'] ?? null,
            ]);

            Log::info('assetmanagement.warranty.added', [
                'business_id' => $businessId,
                'asset_id'    => $assetId,
                'warranty_id' => $warranty->id,
                'months'      => isset($payload['months']) ? (int) $payload['months'] : null,
            ]);

            return $warranty;
        }, [
            'module'      => 'AssetManagement',
            'asset_id'    => $assetId,
            'business_id' => $businessId,
        ]);
    }

    /**
     * Revoga (delete) uma garantia. Span + log.
     */
    public function revogar(int $warrantyId, int $businessId): void
    {
        OtelHelper::spanBiz('assetmanagement.warranty.revoke', function () use ($warrantyId, $businessId) {
            // JOIN pra garantir scope multi-tenant via asset
            $warranty = AssetWarranty::query()
                ->whereHas('asset', fn ($q) => $q->where('business_id', $businessId))
                ->findOrFail($warrantyId);

            $assetId = $warranty->asset_id;
            $warranty->delete();

            Log::info('assetmanagement.warranty.revoked', [
                'business_id' => $businessId,
                'asset_id'    => $assetId,
                'warranty_id' => $warrantyId,
            ]);
        }, [
            'module'      => 'AssetManagement',
            'warranty_id' => $warrantyId,
            'business_id' => $businessId,
        ]);
    }

    /**
     * Lista garantias ativas (end_date >= hoje) de um asset.
     */
    public function ativas(int $assetId, int $businessId): \Illuminate\Support\Collection
    {
        return OtelHelper::spanBiz('assetmanagement.warranty.list_active', function () use ($assetId, $businessId) {
            $asset = Asset::where('business_id', $businessId)->findOrFail($asset_id = $assetId);
            return $asset->warranties()
                ->where('end_date', '>=', now()->toDateString())
                ->orderBy('end_date')
                ->get();
        }, [
            'module'      => 'AssetManagement',
            'asset_id'    => $assetId,
            'business_id' => $businessId,
        ]);
    }

    /**
     * Conta garantias ativas de um asset — útil em widgets dashboard / badges.
     *
     * Wave 27 polish D9.a — span observa agregação (count cheap, mas instrumentar
     * mantém contagem fina pra detectar latência em N+1 futuro). Zero-cost OTel
     * quando `otel.enabled=false` (default Hostinger).
     *
     * Multi-tenant Tier 0 ({@see ADR 0093}): scope via Asset.business_id JOIN.
     *
     * @return int Quantidade de warranties ativas (end_date >= hoje)
     */
    public function contagemAtivas(int $assetId, int $businessId): int
    {
        return OtelHelper::spanBiz('assetmanagement.warranty.count_active', function () use ($assetId, $businessId) {
            $asset = Asset::where('business_id', $businessId)->find($assetId);
            if (! $asset) {
                return 0;
            }
            return $asset->warranties()
                ->where('end_date', '>=', now()->toDateString())
                ->count();
        }, [
            'module'      => 'AssetManagement',
            'asset_id'    => $assetId,
            'business_id' => $businessId,
        ]);
    }

    /**
     * Conta garantias expiradas (end_date < hoje) de um asset — alertas/relatórios.
     *
     * Wave 27 D9.a — span pareado com `count_active` pra observabilidade simétrica.
     * Multi-tenant Tier 0 ({@see ADR 0093}).
     *
     * @return int Quantidade de warranties expiradas
     */
    public function contagemExpiradas(int $assetId, int $businessId): int
    {
        return OtelHelper::spanBiz('assetmanagement.warranty.count_expired', function () use ($assetId, $businessId) {
            $asset = Asset::where('business_id', $businessId)->find($assetId);
            if (! $asset) {
                return 0;
            }
            return $asset->warranties()
                ->where('end_date', '<', now()->toDateString())
                ->count();
        }, [
            'module'      => 'AssetManagement',
            'asset_id'    => $assetId,
            'business_id' => $businessId,
        ]);
    }
}
