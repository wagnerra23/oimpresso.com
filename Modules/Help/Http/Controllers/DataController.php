<?php

namespace Modules\Help\Http\Controllers;

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
                'name' => 'help_module',
                'label' => __('help::lang.help_module'),
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
            $is_help_enabled = $module_util->isModuleInstalled('Help');
            $is_dashboard_enabled = $module_util->isModuleInstalled('Dashboard');
        } else {
            $business_id = session()->get('user.business_id');
            $is_help_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'help_module', 'superadmin_package');
            $is_dashboard_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'dashboard', 'superadmin_package');
        }
        if ($is_help_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->dropdown(
                    __('help::lang.help'),
                    function ($sub) {

                        $sub->url(
                            url('/help/faq'),
                           __('help::lang.faq'),
                            ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'faq']
                        );

                        $sub->url(
                            url('/help/videos'),
                           __('help::lang.videos'),
                            ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'videos']
                        );

                        $sub->url(
                            url('/help/foruns'),
                           __('help::lang.foruns'),
                            ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'foruns']
                        );

                        $sub->url(
                            url('/help/treinamentos'),
                           __('help::lang.treinamentos'),
                            ['icon' => 'fa fas fa-book', 'active' => request()->segment(1) == 'treinamentos']
                        );

                    },
                    ['icon' => 'fas fa-question-circle', 'style' => 'background-color: #2dce89 !important;']
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
