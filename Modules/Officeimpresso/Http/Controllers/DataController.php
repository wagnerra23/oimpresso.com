<?php

namespace Modules\Officeimpresso\Http\Controllers;

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
                'name' => 'officeimpresso_module',
                'label' => __('officeimpresso::lang.officeimpresso_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no UI de Roles do UltimatePOS.
     *
     * Adicionado 2026-04-26 (audit DataController). Officeimpresso é
     * superadmin-only por design, mas registramos permissão pra aparecer
     * no UI de Roles (auditoria) e permitir delegação futura.
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'officeimpresso.access',
                'label' => __('officeimpresso::lang.officeimpresso_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Adds Officeimpresso menus a sidebar admin.
     *
     * Apenas superadmin vê o menu — Officeimpresso é ferramenta interna
     * de gestão de licenças desktop. Ordem 2 (logo depois de Superadmin
     * que tem order 1).
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        if (! auth()->check() || ! auth()->user()->can('superadmin')) {
            return;
        }

        $module_util = new ModuleUtil();
        if (! $module_util->isModuleInstalled('Officeimpresso')) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->dropdown(
                __('officeimpresso::lang.officeimpresso'),
                function ($sub) {
                    $sub->url(
                        action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessall']),
                        __('officeimpresso::lang.businessall'),
                        ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'businessall']
                    );

                    $sub->url(
                        action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores']),
                        __('officeimpresso::lang.computadores'),
                        ['icon' => 'fa fas fa-desktop', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'computadores']
                    );

                    $sub->url(
                        action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'index']),
                        __('officeimpresso::lang.licencas'),
                        ['icon' => 'fa fas fa-key', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'licenca_computador']
                    );

                    $sub->url(
                        action([\Modules\Officeimpresso\Http\Controllers\ClientController::class, 'index']),
                        __('officeimpresso::lang.clients'),
                        ['icon' => 'fa fas fa-user-tag', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'client']
                    );

                    $sub->url(
                        action([\Modules\Officeimpresso\Http\Controllers\LicencaLogController::class, 'index']),
                        'Log de Acesso',
                        ['icon' => 'fa fas fa-clipboard-list', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'licenca_log']
                    );
                },
                ['icon' => 'fas fa-plug', 'style' => 'background-color: #2dce89 !important;']
            )->order(2);
        });
    }
}
