<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Services;

use App\Utils\ProductUtil;
use Modules\ProductCatalogue\Repositories\ProductCatalogueRepository;

/**
 * Wave 16 D4 Architecture — Service layer pra ProductCatalogue (catálogo público QR).
 *
 * Encapsula lógica de montagem do payload renderizado pelas views:
 *   - catalogue.index (listagem agrupada por categoria + descontos vigentes)
 *   - catalogue.show  (detalhe do produto + group prices + descontos por variação + combo)
 *
 * Controllers ficam magros: validam Request, chamam Service, devolvem view com payload.
 * Toda query DB delega pra ProductCatalogueRepository — Service só orquestra + formata.
 *
 * Multi-tenant Tier 0 (ADR 0093): todos os métodos recebem `$businessId` explícito;
 * Repository filtra por business_id em toda query (defesa em profundidade pra rota
 * pública sem auth — atacante pode tentar enumerar tenants via QR scan).
 *
 * @see Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController
 * @see Modules\ProductCatalogue\Repositories\ProductCatalogueRepository
 */
class CatalogueService
{
    public function __construct(
        private ProductCatalogueRepository $repository,
        private ProductUtil $productUtil,
    ) {
    }

    /**
     * Monta payload da tela /catalogue/{business}/{location} (listagem por categoria).
     *
     * @return array{products: \Illuminate\Support\Collection, business: \App\Business, discounts: \Illuminate\Support\Collection, business_location: \App\BusinessLocation, categories: array}
     */
    public function buildIndexPayload(int $businessId, int $locationId): array
    {
        $products = $this->repository->listProductsByLocation($businessId, $locationId);
        $business = $this->repository->findBusinessWithCurrency($businessId);
        $businessLocation = $this->repository->findLocationForBusiness($businessId, $locationId);
        $discounts = $this->formatDiscountAmounts(
            $this->repository->activeDiscounts($businessId, $locationId),
            $business,
        );
        $categories = $this->repository->categoriesDropdown($businessId);

        return [
            'products' => $products,
            'business' => $business,
            'discounts' => $discounts,
            'business_location' => $businessLocation,
            'categories' => $categories,
        ];
    }

    /**
     * Monta payload da tela /show-catalogue/{business}/{product} (detalhe).
     *
     * @return array{product: \App\Product, allowed_group_prices: array, group_price_details: array, combo_variations: array, discounts: array}
     */
    public function buildShowPayload(int $businessId, int $productId, ?int $locationId): array
    {
        $product = $this->repository->findProductWithDetails($businessId, $productId);
        $priceGroups = $this->repository->activePriceGroups($product->business_id);

        $allowedGroupPrices = [];
        foreach ($priceGroups as $key => $value) {
            $allowedGroupPrices[$key] = $value;
        }

        [$groupPriceDetails, $discounts] = $this->collectVariationPricingAndDiscounts(
            $product,
            $locationId,
        );

        $comboVariations = $this->collectComboVariations($product);

        return [
            'product' => $product,
            'allowed_group_prices' => $allowedGroupPrices,
            'group_price_details' => $groupPriceDetails,
            'combo_variations' => $comboVariations,
            'discounts' => $discounts,
        ];
    }

    /**
     * Formata `discount_amount` de cada Discount usando ProductUtil + currency do business.
     */
    private function formatDiscountAmounts(\Illuminate\Support\Collection $discounts, \App\Business $business): \Illuminate\Support\Collection
    {
        foreach ($discounts as $key => $value) {
            $discounts[$key]->discount_amount = $this->productUtil->num_f($value->discount_amount, false, $business);
        }

        return $discounts;
    }

    /**
     * Itera variações coletando group_prices (price_group_id => price_inc_tax) +
     * discounts por variação (via ProductUtil::getProductDiscount).
     *
     * @return array{0: array, 1: array} [$groupPriceDetails, $discounts]
     */
    private function collectVariationPricingAndDiscounts(\App\Product $product, ?int $locationId): array
    {
        $groupPriceDetails = [];
        $discounts = [];

        foreach ($product->variations as $variation) {
            foreach ($variation->group_prices as $groupPrice) {
                $groupPriceDetails[$variation->id][$groupPrice->price_group_id] = $groupPrice->price_inc_tax;
            }

            $discounts[$variation->id] = $this->productUtil->getProductDiscount(
                $product,
                $product->business_id,
                $locationId,
                false,
                null,
                $variation->id,
            );
        }

        return [$groupPriceDetails, $discounts];
    }

    /**
     * Extrai combo_variations quando product->type=='combo'. Vazio pros demais tipos.
     */
    private function collectComboVariations(\App\Product $product): array
    {
        if ($product->type !== 'combo') {
            return [];
        }

        return $this->productUtil->__getComboProductDetails(
            $product['variations'][0]->combo_variations,
            $product->business_id,
        );
    }
}
