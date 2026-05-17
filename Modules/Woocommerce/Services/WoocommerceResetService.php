<?php

namespace Modules\Woocommerce\Services;

use App\Category;
use App\Media;
use App\Product;
use App\Util\OtelHelper;
use App\Variation;
use App\VariationTemplate;
use Illuminate\Support\Facades\Log;
use Modules\Woocommerce\Utils\WoocommerceUtil;

/**
 * WoocommerceResetService — reset de mapeamentos POS ↔ WooCommerce.
 *
 * Antes (D4 baixo): Controller `resetCategories()` e `resetProducts()` faziam
 * UPDATE direto em 5 tabelas (Product, Variation, VariationTemplate, Media,
 * Category) inline + log sync. ~80 linhas Eloquent por método.
 *
 * Depois: lógica isolada em Service, Controller chama 1 linha.
 *
 * Multi-tenant Tier 0 ([ADR 0093]): TODA query filtra `business_id` — Service
 * é job-safe (recebe `$businessId` no constructor da chamada, não session()).
 *
 * Atenção: NÃO usamos `forceDelete()` — reset apenas NULL-ifica colunas
 * `woocommerce_*_id`. Linhas POS originais permanecem intactas.
 */
class WoocommerceResetService
{
    public function __construct(private readonly WoocommerceUtil $woocommerceUtil)
    {
    }

    /**
     * Reseta mapeamento POS ↔ WooCommerce para categorias do business.
     */
    public function resetarCategorias(int $businessId, int $userId): array
    {
        // D9 Wave 18 — OTel span (reset destrutivo — visibilidade alta)
        return OtelHelper::span('woocommerce.reset.categories', [
            'business_id' => $businessId,
            'user_id' => $userId,
        ], function () use ($businessId, $userId) {
            try {
                Category::where('business_id', $businessId)
                    ->update(['woocommerce_cat_id' => null]);

                $this->woocommerceUtil->createSyncLog($businessId, $userId, 'categories', 'reset', null);

                return [
                    'success' => 1,
                    'msg' => __('woocommerce::lang.cat_reset_success'),
                ];
            } catch (\Exception $e) {
                Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                return [
                    'success' => 0,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }
        });
    }

    /**
     * Reseta mapeamento POS ↔ WooCommerce para produtos + variações + medias.
     *
     * 5 tabelas tocadas com filtro `business_id` explícito (Tier 0):
     *   1. products              → woocommerce_product_id, woocommerce_media_id
     *   2. variations            → woocommerce_variation_id (via product_ids)
     *   3. variation_templates   → woocommerce_attr_id
     *   4. medias                → woocommerce_media_id
     *   5. woocommerce_sync_logs → audit do reset
     */
    public function resetarProdutos(int $businessId, int $userId): array
    {
        // D9 Wave 18 — OTel span (reset destrutivo 5 tabelas — observability crítica)
        return OtelHelper::span('woocommerce.reset.products', [
            'business_id' => $businessId,
            'user_id' => $userId,
        ], function () use ($businessId, $userId) {
            try {
                Product::where('business_id', $businessId)
                    ->update([
                        'woocommerce_product_id' => null,
                        'woocommerce_media_id' => null,
                    ]);

                // Variations precisam ser filtradas via product_ids do business (tabela
                // variations não tem business_id direto — pivot via products).
                $productIds = Product::where('business_id', $businessId)->pluck('id');

                if ($productIds->isNotEmpty()) {
                    Variation::whereIn('product_id', $productIds)
                        ->update(['woocommerce_variation_id' => null]);
                }

                VariationTemplate::where('business_id', $businessId)
                    ->update(['woocommerce_attr_id' => null]);

                Media::where('business_id', $businessId)
                    ->update(['woocommerce_media_id' => null]);

                $this->woocommerceUtil->createSyncLog($businessId, $userId, 'all_products', 'reset', null);

                return [
                    'success' => 1,
                    'msg' => __('woocommerce::lang.prod_reset_success'),
                ];
            } catch (\Exception $e) {
                Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                return [
                    'success' => 0,
                    'msg' => 'Erro interno ao resetar produtos.',
                ];
            }
        });
    }
}
