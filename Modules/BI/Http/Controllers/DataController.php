<?php

namespace Modules\BI\Http\Controllers;

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
                'name' => 'bi_module',
                'label' => __('bi::lang.bi_module'),
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
            $is_bi_enabled = $module_util->isModuleInstalled('BI');
            $is_dashboard_enabled = $module_util->isModuleInstalled('Dashboard');
        } else {
            $business_id = session()->get('user.business_id');
            $is_bi_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'bi_module', 'superadmin_package');
            $is_dashboard_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'dashboard', 'superadmin_package');
        }
        if ($is_bi_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->dropdown(
                    __('bi::lang.bi'),
                    function ($sub) {
                        if (auth()->user()->can('superadmin')) {
                            $sub->url(
                                action('\Modules\BI\Http\Controllers\ClientController@index'),
                               __('bi::lang.clients'),
                                ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'bi' && request()->segment(2) == 'api']
                            );
                        }
                        $sub->url(
                            url('\docs'),
                           __('bi::lang.documentation'),
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
