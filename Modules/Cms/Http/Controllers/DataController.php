<?php

namespace Modules\Cms\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Defines module as a superadmin package.
     *
     * Adicionado 2026-04-26 (audit DataController) — antes Cms era 100%
     * superadmin-gated mas faltava o registro formal de pacote.
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'cms_module',
                'label' => __('cms::lang.cms_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no UI de Roles do UltimatePOS.
     *
     * Adicionado 2026-04-26 (audit DataController). Cms gerencia páginas,
     * blogs e contact-us — registramos permissão `cms.access` mínima.
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'cms.access',
                'label' => __('cms::lang.cms_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Adds cms menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        $business_id = session()->get('user.business_id');

        if (auth()->user()->can('superadmin')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'index'], ['type' => 'page']),
                    __('cms::lang.cms'),
                    ['icon' => 'fas fa-window-restore fa', 'style' => config('app.env') == 'demo' ? 'background-color: #9E458B !important;' : '', 'active' => request()->segment(1) == 'cms']
                )->order(5);
            });
        }
    }
}
