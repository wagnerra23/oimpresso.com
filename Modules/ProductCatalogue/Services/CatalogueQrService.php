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

        if (! $this->moduleUtil->hasThePermissionInSubscription($businessId, 'productcatalogue_module')) {
            return false;
        }

        // Camada 3 (permissão do papel): decisão PERM-CQR, [W] 2026-10-07 — o catálogo é
        // vitrine de produto, então quem não vê produto não gera o QR que publica preço.
        // O dono do negócio passa pelo Gate::before (Admin#{business_id}).
        return $user->can('product.view');
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

    /**
     * Props da Page Inertia `ProductCatalogue/CatalogueQr`.
     *
     * Locais vêm do mesmo `BusinessLocation::forDropdown` da Blade (só os locais do negócio da
     * sessão e, dentro dele, os permitidos ao usuário). `link_base` é montado aqui, com o id do
     * negócio da SESSÃO: a tela só acrescenta `/{location_id}` e nunca escolhe o negócio.
     *
     * @return array{locais: list<array{id:int,nome:string}>, negocio: array{nome:string,logo_url:?string}, link_base:string, qr_script:string}
     */
    public function buildPagePayload(int $businessId): array
    {
        $payload = $this->buildQrPayload($businessId);
        $business = $payload['business'];

        $locais = [];
        foreach ($payload['business_locations'] as $id => $nome) {
            $locais[] = ['id' => (int) $id, 'nome' => (string) $nome];
        }

        $logo = trim((string) ($business->logo ?? ''));

        return [
            'locais' => $locais,
            'negocio' => [
                'nome' => (string) $business->name,
                'logo_url' => $logo !== '' ? asset('uploads/business_logos/'.$logo) : null,
            ],
            'link_base' => url('catalogue/'.$businessId),
            'qr_script' => asset('modules/productcatalogue/plugins/easy.qrcode.min.js'),
        ];
    }
}
