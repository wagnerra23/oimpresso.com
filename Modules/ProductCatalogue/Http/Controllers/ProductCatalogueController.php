<?php

namespace Modules\ProductCatalogue\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\ProductCatalogue\Services\CatalogueQrService;
use Modules\ProductCatalogue\Services\CatalogueService;

/**
 * Wave 16 D4 Architecture — Controller refatorado pra ser MAGRO (single responsibility).
 *
 * Responsabilidades exclusivas do Controller (≤30 linhas/método):
 *   - Receber HTTP Request (validações via FormRequest separado quando aplicável)
 *   - Delegar lógica de negócio aos Services injetados via constructor
 *   - Devolver Response/View
 *
 * Lógica de negócio (queries Eloquent, formatação, autorização) vive em:
 *   - {@see CatalogueService} — telas públicas index/show
 *   - {@see CatalogueQrService} — tela admin QR generator
 *   - {@see \Modules\ProductCatalogue\Repositories\ProductCatalogueRepository} — DB
 *
 * Pattern canônico: ADR 0011 (padrão Jana/Repair), inspirado em Modules/Jana/Services.
 */
class ProductCatalogueController extends Controller
{
    public function __construct(
        private CatalogueService $catalogueService,
        private CatalogueQrService $qrService,
    ) {
    }

    /**
     * GET /catalogue/{business_id}/{location_id} — listagem pública agrupada por categoria.
     *
     * @return Response
     */
    public function index($business_id, $location_id)
    {
        $payload = $this->catalogueService->buildIndexPayload((int) $business_id, (int) $location_id);

        return view('productcatalogue::catalogue.index')->with($payload);
    }

    /**
     * GET /show-catalogue/{business_id}/{product_id} — detalhe público do produto.
     *
     * @param  int  $business_id
     * @param  int  $id
     * @return Response
     */
    public function show($business_id, $id)
    {
        $locationId = request()->input('location_id');
        $payload = $this->catalogueService->buildShowPayload(
            (int) $business_id,
            (int) $id,
            $locationId !== null ? (int) $locationId : null,
        );

        return view('productcatalogue::catalogue.show')->with($payload);
    }

    /**
     * GET /product-catalogue/catalogue-qr — tela admin pra gerar QR codes do catálogo.
     *
     * @return \Inertia\Response
     */
    public function generateQr()
    {
        $businessId = (int) request()->session()->get('user.business_id');

        if (! $this->qrService->authorizeAccess($businessId, auth()->user())) {
            abort(403, 'Unauthorized action.');
        }

        // Inertia desde a thread modulos-faltantes/04 (Catálogo QR). A Blade
        // catalogue/generate_qr.blade.php fica no repo até o cutover; a vitrine pública
        // (index/show acima) segue Blade e não muda.
        return Inertia::render('ProductCatalogue/CatalogueQr', $this->qrService->buildPagePayload($businessId));
    }
}
