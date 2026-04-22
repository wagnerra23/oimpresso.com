<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Modules\Dashboard\Http\Controllers\DashboardController;

use Menu;


class DataController extends Controller
{
    public function superadmin_package()
    {
        return [
            [
                'name' => 'officeimpresso_module',
                'label' => __('officeimpresso::lang.officeimpresso_module'),
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
            $is_officeimpresso_enabled = $module_util->isModuleInstalled('Officeimpresso');
            $is_dashboard_enabled = $module_util->isModuleInstalled('Dashboard');
        } else {
            $business_id = session()->get('user.business_id');
            $is_officeimpresso_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'officeimpresso_module', 'superadmin_package');
            $is_dashboard_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'dashboard', 'superadmin_package');
        }
        if ($is_officeimpresso_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->dropdown(
                    __('officeimpresso::lang.officeimpresso'),
                    function ($sub) {
                        if (auth()->user()->can('superadmin')) {
                            $sub->url(
                                action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessall']),
                               __('officeimpresso::lang.businessall'),
                                ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'businesssall']
                            );
                            
                            $sub->url(
                                url('http://sistema.wr2.com.br:19000/ping'),
                               __('officeimpresso::lang.ping'),
                                ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'ping']
                            );
                        }
                        $sub->url(
                            action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class,'computadores']),
                           __('officeimpresso::lang.computadores'),
                            ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'computadores']
                        );
                        // $sub->url(
                        //     action('\Modules\Officeimpresso\Http\Controllers\LicencaLogController@index'),
                        //    __('officeimpresso::lang.loglicencas'),
                        //     ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'loglicencas']
                        // );
                        $sub->url(
                            url('/officeimpresso/docs'),
                           __('officeimpresso::lang.documentation'),
                            ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'docs']
                        );
                    },
                    ['icon' => 'fas fa-plug']
                )->order(89);
            });
           
        };
        if ($is_dashboard_enabled && auth()->user()->can('dashboard.access')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    action([\Modules\Dashboard\Http\Controllers\DashboardController::class,'index']),
                    __('Dashboard'),
                    ['icon' => 'fa fas fa-list', 'active' => request()->segment(1) == 'expenses' && request()->segment(2) == null]
                )->order(86);
            });
        } 
    }
}
