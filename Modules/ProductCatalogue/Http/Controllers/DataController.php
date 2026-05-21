<?php

namespace Modules\ProductCatalogue\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Defines module as a superadmin package.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'productcatalogue_module',
                'label' => __('productcatalogue::lang.productcatalogue_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no UI de Roles do UltimatePOS.
     *
     * Adicionado 2026-04-26 (audit DataController) pra permitir
     * delegação granular do acesso ao catálogo QR.
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'productcatalogue.access',
                'label' => __('productcatalogue::lang.productcatalogue_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Adds Catalogue QR menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_productcatalogue_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'productcatalogue_module', 'superadmin_package');

        if ($is_productcatalogue_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                // ADR 0180 Fase 4 Wave A VENDER (2026-05-21): entry principal
                // Catálogo QR declara atributos extras propagados pelo
                // LegacyMenuAdapter pro frontend Sidebar.tsx (v3 5 grupos canon):
                //  - `shortcut` G K → atalho kbd canônico (overlay Fase 8)
                //  - `primary`     → botão "Gerar QR" (ação principal do módulo)
                //  - `ghosts`      → 1 sub-view canon (módulo single-page admin)
                //
                // group: legacy default (sem key) → LEGACY_GROUP_MAP frontend
                // mapeia pra 'vender' v3 (cliente escaneia QR pra comprar/ver
                // catálogo público — pertence à coluna venda/storefront).
                //
                // Módulo é single-page admin (apenas /product-catalogue/catalogue-qr)
                // — as rotas /catalogue/{biz}/{loc} e /show-catalogue/* são PÚBLICAS
                // (sem auth, sem sidebar). Ghosts mantém shape contratual mesmo com
                // 1 entry: Sidebar.tsx aceita arrays single-element graciosamente
                // (espelha contrato App\Sidebar\SidebarGhost). Quando feature
                // expandir (ex tela de configuração de tema do QR), basta apendar.
                $menu->url(
                    action([\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'generateQr']),
                    __('productcatalogue::lang.catalogue_qr'),
                    [
                        'icon'     => 'fa fas fa-qrcode',
                        'active'   => request()->segment(1) == 'product-catalogue',
                        'style'    => config('app.env') == 'demo' ? 'background-color: #ff851b;' : '',
                        'shortcut' => 'G K',
                        'primary'  => [
                            'label'    => 'Gerar QR',
                            'href'     => '/product-catalogue/catalogue-qr',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'catalogue-qr', 'label' => 'Catálogo QR', 'href' => '/product-catalogue/catalogue-qr'],
                        ],
                    ]
                )->order(95);
            });
        }
    }
}
