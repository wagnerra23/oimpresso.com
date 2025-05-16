<?php

namespace Modules\Grow\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Superadmin package permissions
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'grow_module',
                'label' => __('grow::lang.grow_module'),
                'default' => false,
            ],
            [
                'name' => 'grow_max_token',
                'label' => __('grow::lang.grow_max_token'),
                'default' => false,
                'field_type' => 'number',
                'tooltip' => __('grow::lang.max_token_tooltip')
            ],
        ];
    }

    /**
     * Adds menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();

        $is_grow_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'grow_module');

        $commonUtil = new Util();
        $is_admin = $commonUtil->is_admin(auth()->user(), $business_id);

        if (auth()->user()->can('grow.access_grow_module') && $is_grow_enabled) {
            Menu::modify(
                'admin-sidebar-menu',
                function ($menu) {
                    $menu->url(action([\Modules\Grow\Http\ControllersController::class, 'index']), __('grow::lang.grow'), ['icon' => 'fas fa-robot', 'active' => request()->segment(1) == 'grow'])->order(50);
                    // $menu->url(action([\Modules\Grow\Http\Controllers\Tasks::class, 'index']), __('grow::lang.tasks'), ['icon' => 'fas fa-tasks', 'active' => request()->segment(1) == 'tasks'])->order(50);       // Tarefas
                    // $menu->url(action([\Modules\Grow\Http\Controllers\Tickets::class, 'index']), __('grow::lang.tickets'), ['icon' => 'fas fa-tasks', 'active' => request()->segment(1) == 'tickets'])->order(50); // Chamados
                    // $menu->url(action([\Modules\Grow\Http\Controllers\Team::class, 'index']), __('grow::lang.team'), ['icon' => 'fas fa-robot', 'active' => request()->segment(1) == 'team'])->order(50);          // Times   
                    // $menu->url(action([\Modules\Grow\Http\Controllers\Notes::class, 'index']), __('grow::lang.notes'), ['icon' => 'fas fa-robot', 'active' => request()->segment(1) == 'notes'])->order(50);       // Gerenciamendo de Conhecimento
                }  
            );
        }
    }

    /**
     * Defines user permissions for the module.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'grow.access_grow_module',
                'label' => __('grow::lang.access_grow_module'),
                'default' => false,
            ]      
        ];
    }
}
