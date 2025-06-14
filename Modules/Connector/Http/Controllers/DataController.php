<?php

namespace Modules\Connector\Http\Controllers;

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
                'name' => 'connector_module',
                'label' => __('connector::lang.connector_module'),
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
            $is_connector_enabled = $module_util->isModuleInstalled('Connector');
            $is_dashboard_enabled = $module_util->isModuleInstalled('Dashboard');
        } else {
            $business_id = session()->get('user.business_id');
            $is_connector_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'connector_module', 'superadmin_package');
            $is_dashboard_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'dashboard', 'superadmin_package');
        }
        if ($is_connector_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->dropdown(
                    __('connector::lang.connector'),
                    function ($sub) {
                        if (auth()->user()->can('superadmin')) {
                            $sub->url(
                                action('\Modules\Connector\Http\Controllers\ClientController@index'),
                               __('connector::lang.clients'),
                                ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'connector' && request()->segment(2) == 'api']
                            );
                        }
                        $sub->url(
                            url('\public\docs'),
                           __('connector::lang.documentation'),
                            ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'docs']
                        );
                    },
                    ['icon' => 'fas fa-plug', 'style' => 'background-color: #2dce89 !important;']
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
