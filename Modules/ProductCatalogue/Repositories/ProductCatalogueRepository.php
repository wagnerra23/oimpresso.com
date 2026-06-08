<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Repositories;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Discount;
use App\Product;
use App\SellingPriceGroup;
use Illuminate\Support\Collection;

/**
 * Wave 16 D4 Architecture — Repository pattern pra ProductCatalogue.
 *
 * Encapsula TODAS as queries Eloquent do catálogo público (App\Product, App\Discount,
 * App\Category, App\Business, App\BusinessLocation, App\SellingPriceGroup). Centraliza
 * filtros multi-tenant (`business_id`) e relações eager-loaded num único lugar,
 * permitindo Services chamarem métodos semânticos em vez de queries inline.
 *
 * Pattern canônico inspirado em Modules/Jana/Services + ADR 0011 (padrão Jana/Repair).
 *
 * Multi-tenant Tier 0 (ADR 0093): toda query filtra por `business_id` explícito —
 * App\Product já tem global scope mas reforçamos defensivamente no catálogo público
 * (rota SEM AUTH onde atacante pode enumerar tenants).
 *
 * @see Modules\ProductCatalogue\Services\CatalogueService
 * @see Modules\ProductCatalogue\Services\CatalogueQrService
 * @see Modules\ProductCatalogue\Tests\Feature\PublicCatalogueSecurityTest
 */
class ProductCatalogueRepository
{
    /**
     * Lista produtos vendáveis do business+location agrupados por categoria.
     */
    public function listProductsByLocation(int $businessId, int $locationId): Collection
    {
        return Product::where('business_id', $businessId)
            ->whereHas('product_locations', function ($q) use ($locationId) {
                $q->where('product_locations.location_id', $locationId);
            })
            ->ProductForSales()
            ->with(['variations', 'variations.product_variation', 'category'])
            ->get()
            ->groupBy('category_id');
    }

    /**
     * Busca produto com TODAS as relações usadas na tela de detalhe (catalogue.show).
     * Throws ModelNotFoundException se product_id não pertence ao business_id (anti-enumeration).
     */
    public function findProductWithDetails(int $businessId, int $productId): Product
    {
        return Product::with([
            'brand',
            'unit',
            'category',
            'sub_category',
            'product_tax',
            'variations',
            'variations.product_variation',
            'variations.group_prices',
            'variations.media',
            'product_locations',
            'warranty',
        ])
            ->where('business_id', $businessId)
            ->findOrFail($productId);
    }

    /**
     * Business com currency (header do catálogo).
     */
    public function findBusinessWithCurrency(int $businessId): Business
    {
        return Business::with(['currency'])->findOrFail($businessId);
    }

    /**
     * BusinessLocation validando que pertence ao business (anti cross-tenant).
     */
    public function findLocationForBusiness(int $businessId, int $locationId): BusinessLocation
    {
        return BusinessLocation::where('business_id', $businessId)->findOrFail($locationId);
    }

    /**
     * Descontos ativos vigentes do business+location ordenados por prioridade desc.
     */
    public function activeDiscounts(int $businessId, int $locationId): Collection
    {
        $now = \Carbon::now()->toDateTimeString();

        return Discount::where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->where('is_active', 1)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Categorias 'product' do business no formato dropdown (id => name).
     */
    public function categoriesDropdown(int $businessId): array
    {
        return Category::forDropdown($businessId, 'product');
    }

    /**
     * Selling price groups ativos do business (id => name).
     */
    public function activePriceGroups(int $businessId): \Illuminate\Support\Collection
    {
        return SellingPriceGroup::where('business_id', $businessId)
            ->active()
            ->pluck('name', 'id');
    }

    /**
     * BusinessLocations dropdown do business (pra tela QR generator).
     */
    public function locationsDropdown(int $businessId): array
    {
        return BusinessLocation::forDropdown($businessId);
    }

    /**
     * Business raw (sem currency) — usado em telas admin onde só nome basta.
     */
    public function findBusiness(int $businessId): Business
    {
        return Business::findOrFail($businessId);
    }
}
