<?php

namespace Modules\Chat\Http\Controllers;

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
                'name' => 'chat_module',
                'label' => __('chat::lang.chat_module'),
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
            $is_chat_enabled = $module_util->isModuleInstalled('Chat');
            $is_dashboard_enabled = $module_util->isModuleInstalled('Dashboard');
        } else {
            $business_id = session()->get('user.business_id');
            $is_chat_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'chat_module', 'superadmin_package');
            $is_dashboard_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'dashboard', 'superadmin_package');
        }
        if ($is_chat_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->dropdown(
                    __('chat::lang.chat'),
                    function ($sub) {
                        if (auth()->user()->can('superadmin')) {
                            $sub->url(
                                url('/chat/evolution-api'),
                               __('chat::lang.evolution-api'),
                                ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'evolution-api' && request()->segment(2) == 'api']
                            );
        
                            $sub->url(
                                url('/superadmin/typebot'),
                                'Typebot',
                                ['icon' => 'fa fa-cogs', 'active' => request()->segment(2) == 'typebot']
                            );             
                            
                            $sub->url(
                                url('/superadmin/minio'),
                                'MinIO S3',
                                ['icon' => 'fa fa-cogs', 'active' => request()->segment(2) == 'minio']
                            );     
                        }
                        
                        $sub->url(
                            url('/chat/conversas'),
                            __('chat::lang.conversations'),
                            ['icon' => 'fas fa-comments', 'active' => request()->segment(1) == 'conversas']
                        );
                        
                        $sub->url(
                            url('/chat/contatos'),
                            __('chat::lang.contacts'),
                            ['icon' => 'fas fa-address-book', 'active' => request()->segment(1) == 'contatos']
                        );
                        
                        $sub->url(
                            url('/chat/relatorios'),
                            __('chat::lang.reports'),
                            ['icon' => 'fas fa-chart-line', 'active' => request()->segment(1) == 'relatorios']
                        );
                        
                        $sub->url(
                            url('/chat/campanhas'),
                            __('chat::lang.campaigns'),
                            ['icon' => 'fas fa-bullhorn', 'active' => request()->segment(1) == 'campanhas']
                        );
                        
                        $sub->url(
                            url('/chat/central-de-ajuda'),
                            __('chat::lang.help_center'),
                            ['icon' => 'fas fa-question-circle', 'active' => request()->segment(1) == 'central-de-ajuda']
                        );
                        
                        $sub->url(
                            url('/chat/configuracoes'),
                            __('chat::lang.settings'),
                            ['icon' => 'fas fa-cogs', 'active' => request()->segment(1) == 'configuracoes']
                        );
                        
                        $sub->url(
                            url('/chat/perfil'),
                            __('chat::lang.profile'),
                            ['icon' => 'fas fa-bell', 'active' => request()->segment(1) == 'perfil']
                        );
                        
                    },
                    ['icon' => 'fas fa-comments', 'style' => 'background-color: #a5b8ff  !important;']
                )->order(10);
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
