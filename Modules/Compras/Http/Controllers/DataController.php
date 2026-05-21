<?php

namespace Modules\Compras\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;

/**
 * DataController do módulo Compras.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\Compras\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * Wave 1: registra permissions canônicas + entry "Compras" no sidebar.
 * Group visual via SIDEBAR_GROUPS frontend (skill `sidebar-menu-arch`), NÃO
 * Menu::dropdown cross-módulo.
 *
 * @see Modules/PontoWr2/Http/Controllers/DataController.php (template)
 * @see Modules/NfeBrasil/Http/Controllers/DataController.php (referência)
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para Superadmin > Packages.
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'compras_module',
                'label' => 'Módulo Compras',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem na tela de Roles do UltimatePOS.
     *
     * Tier 0 ADR 0093: hotfix #624 — Role::firstOrCreate com suffix `#{biz_id}`
     * é responsabilidade do PermissionsSeeder. Aqui declara apenas o catálogo.
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'compras.view',
                'label' => 'Compras: visualizar cockpit + lista',
                'default' => false,
            ],
            [
                'value' => 'compras.create',
                'label' => 'Compras: criar compra manual',
                'default' => false,
            ],
            [
                'value' => 'compras.edit',
                'label' => 'Compras: editar compra (status, lines, payments)',
                'default' => false,
            ],
            [
                'value' => 'compras.delete',
                'label' => 'Compras: excluir compra',
                'default' => false,
            ],
            [
                'value' => 'compras.import_xml',
                'label' => 'Compras: importar XML DF-e como compra',
                'default' => false,
            ],
        ];
    }

    /**
     * Sidebar entry "Compras".
     *
     * Group visual fica em SIDEBAR_GROUPS no frontend (Components/cockpit/Sidebar.tsx).
     * Aqui só publica a entrada base — agrupamento é responsabilidade da camada UI.
     */
    public function modifyAdminMenu()
    {
        $moduleUtil = new ModuleUtil();
        $business_id = session('user.business_id');

        if (! $business_id) {
            return;
        }

        $is_enabled = $moduleUtil->isModuleEnabled('compras_module', $business_id);

        if (! $is_enabled) {
            return;
        }

        \Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                action([\Modules\Compras\Http\Controllers\ComprasController::class, 'index']),
                'Compras',
                ['icon' => 'fa fas fa-shopping-cart', 'active' => request()->is('compras*')]
            )->order(45);
        });
    }
}
