<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Services;

use App\Util\OtelHelper;
use App\Utils\ModuleUtil;
use Modules\ProductCatalogue\Repositories\ProductCatalogueRepository;

/**
 * Wave 16 D4 Architecture — Service da tela admin "Catalogue QR Generator".
 *
 * Responsável por:
 *   - Autorizar acesso (superadmin OR subscription productcatalogue_module)
 *   - Montar payload da tela /product-catalogue/catalogue-qr (locations + business)
 *
 * Isolar isso do Controller libera o Controller pra ser puro orquestrador HTTP
 * (validar Request, delegar Service, devolver View) — pattern Controllers magros.
 *
 * Multi-tenant Tier 0 (ADR 0093): recebe `$businessId` da sessão (Controller passa)
 * e Repository filtra todas as queries por ele.
 *
 * @see Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::generateQr
 * @see Modules\ProductCatalogue\Repositories\ProductCatalogueRepository
 */
class CatalogueQrService
{
    public function __construct(
        private ProductCatalogueRepository $repository,
        private ModuleUtil $moduleUtil,
    ) {
    }

    /**
     * Autoriza acesso à tela QR generator:
     *   - superadmin → sempre permite
     *   - else → exige permission productcatalogue_module na subscription do business
     */
    public function authorizeAccess(int $businessId, \App\User $user): bool
    {
        if ($user->can('superadmin')) {
            return true;
        }

        return (bool) $this->moduleUtil->hasThePermissionInSubscription($businessId, 'productcatalogue_module');
    }

    /**
     * Monta payload da tela /product-catalogue/catalogue-qr.
     *
     * D9 observabilidade (Wave 17): wrapped em OtelHelper::spanBiz pra trace.
     *
     * @return array{business_locations: array, business: \App\Business}
     */
    public function buildQrPayload(int $businessId): array
    {
        return OtelHelper::spanBiz('product_catalogue.build_qr_payload', fn () => [
            'business_locations' => $this->repository->locationsDropdown($businessId),
            'business' => $this->repository->findBusiness($businessId),
        ], [
            'business_id' => $businessId,
        ]);
    }
}
