<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Services;

use App\Util\OtelHelper;
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
     * D9 observabilidade (Wave 17): wrapped em OtelHelper::spanBiz pra trace
     * end-to-end (catálogo público QR é hot path — múltiplas queries Repository).
     *
     * @return array{products: \Illuminate\Support\Collection, business: \App\Business, discounts: \Illuminate\Support\Collection, business_location: \App\BusinessLocation, categories: array}
     */
    public function buildIndexPayload(int $businessId, int $locationId): array
    {
        return OtelHelper::spanBiz('product_catalogue.build_index_payload', function () use ($businessId, $locationId): array {
            $products = $this->repository->listProductsByLocation($businessId, $locationId);
            $business = $this->repository->findBusinessWithCurrency($businessId);
            $discounts = $this->repository->activeDiscounts($businessId, $locationId);

            $this->anexarDescontoDoPdv($products, $business, $businessId, $locationId, $discounts->isNotEmpty());

            return [
                'products' => $products,
                'business' => $business,
                'business_location' => $this->repository->findLocationForBusiness($businessId, $locationId),
                'discounts' => $discounts,
                'categories' => $this->repository->categoriesDropdown($businessId),
            ];
        }, [
            'business_id'  => $businessId,
            'location_id'  => $locationId,
        ]);
    }

    /**
     * Põe em cada produto da listagem (`desconto_vitrine`) o desconto que o PDV aplicaria.
     *
     * Usa o próprio ProductUtil::getProductDiscount, sem grupo de preço (o mesmo que o detalhe
     * chama): marca e categoria juntas, prioridade, `spg` nulo e desconto por variação. Para
     * produto não variável passa a variação única, como o PDV ao vender; produto variável fica
     * com o desconto do produto e o detalhe mostra o de cada variação.
     *
     * Uma consulta por produto — só quando há algum desconto vigente no local. Sem desconto
     * vigente (o caso comum) a listagem não paga nada.
     */
    private function anexarDescontoDoPdv(
        \Illuminate\Support\Collection $porCategoria,
        \App\Business $business,
        int $businessId,
        int $locationId,
        bool $haDescontoVigente,
    ): void {
        if (! $haDescontoVigente) {
            return;
        }

        $formato = new FormatoVitrine($business);
        foreach ($porCategoria as $produtos) {
            foreach ($produtos as $product) {
                $variationId = $product->type !== 'variable' ? $product->variations->first()?->id : null;
                // getProductDiscount devolve o Discount ou null (é um `->first()`), mas o docblock
                // dele declara `obj`, que o PHPStan lê como classe inexistente.
                /** @var \App\Discount|null $desconto */
                $desconto = $this->productUtil->getProductDiscount($product, $businessId, $locationId, false, null, $variationId);
                if ($desconto instanceof \App\Discount) {
                    $desconto->setAttribute('selo', $formato->seloDesconto($desconto));
                    $product->desconto_vitrine = $desconto;
                } else {
                    $product->desconto_vitrine = null;
                }
            }
        }
    }

    /**
     * Monta payload da tela /show-catalogue/{business}/{product} (detalhe).
     *
     * D9 (Wave 17): span dedicado mede latência por produto + business_id Tier 0.
     *
     * @return array{product: \App\Product, allowed_group_prices: array, group_price_details: array, combo_variations: array, discounts: array}
     */
    public function buildShowPayload(int $businessId, int $productId, ?int $locationId): array
    {
        return OtelHelper::spanBiz('product_catalogue.build_show_payload', function () use ($businessId, $productId, $locationId): array {
            $product = $this->repository->findProductWithDetails($businessId, $productId, $locationId);
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
            $this->anexarSelo($discounts, $this->repository->findBusinessWithCurrency($businessId));

            return [
                'product' => $product,
                'allowed_group_prices' => $allowedGroupPrices,
                'group_price_details' => $groupPriceDetails,
                'combo_variations' => $comboVariations,
                'discounts' => $discounts,
            ];
        }, [
            'business_id' => $businessId,
            'product_id'  => $productId,
            'location_id' => $locationId,
        ]);
    }

    /**
     * Anexa a cada desconto o texto do selo (`selo`), conforme o tipo: porcentagem ou dinheiro.
     * Não reescreve `discount_amount`: o número segue cru para quem precisar calcular.
     */
    private function anexarSelo(iterable $discounts, \App\Business $business): iterable
    {
        $formato = new FormatoVitrine($business);
        foreach ($discounts as $discount) {
            if (! empty($discount)) {
                $discount->selo = $formato->seloDesconto($discount);
            }
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
