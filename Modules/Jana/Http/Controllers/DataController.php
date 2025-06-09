<?php

namespace Modules\Jana\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Menu;

class DataController extends Controller
{
    public function superadmin_package()
    {
        return [
            [
                'name' => 'jana_module',
                'label' => __('jana::lang.jana_module'),
                'default' => false
            ]
        ];
    }

    /**
     * Adds Connectoe menus
     * @return null
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();
        
        if (auth()->user()->can('superadmin')) {
            $is_jana_enabled = $module_util->isModuleInstalled('Jana');
            $is_dashboard_enabled = $module_util->isModuleInstalled('Dashboard');
        } else {
            $business_id = session()->get('user.business_id');
            $is_jana_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'jana_module', 'superadmin_package');
            $is_dashboard_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'dashboard', 'superadmin_package');
        }
        if ($is_jana_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->dropdown(
                    __('jana::lang.jana'),
                    function ($sub) {
                        if (auth()->user()->can('superadmin')) {
                            $sub->url(
                                url('/jana/n8n'),
                                'Automação n8n',
                                ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'jana' && request()->segment(2) == 'n8n']
                            );

                            $sub->url(
                                url('/jana/flowise'),
                                'Automação Flowise',
                                ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'jana' && request()->segment(2) == 'flowise']
                            );
                        }
                        // - Explorar
                        // - Estúdio
                        // - Conhecimento
                        // - Ferramentas
                        // - Configurações
                        // - Espaço de trabalho(Poup)

                        $sub->url(
                            url('/jana/jana'),
                            'Assistente Jana.IA',
                            ['icon' => 'fa fa-brain', 'active' => request()->segment(2) == 'jana']
                        );

                        $sub->url(
                            url('/jana/docs'),
                           __('jana::lang.documentation'),
                            ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'docs']
                        );
                    },
                    ['icon' => 'fas fa-magic', 'style' => 'background-color: #2dce89 !important;']
                )->order(89);
            });
           
        };
        if ($is_dashboard_enabled && auth()->user()->can('dashboard.access')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    action('\Modules\Dashboard\Http\Controllers\DashboardController@index'),
                    __('Dashboard'),
                    ['icon' => 'fa fas fa-list', 'active' => request()->segment(1) == 'expenses' && request()->segment(2) == null]
                )->order(86);
            });
        } 
    }
}
