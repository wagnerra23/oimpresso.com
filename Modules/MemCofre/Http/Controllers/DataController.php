<?php

namespace Modules\MemCofre\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — integrações com UltimatePOS.
 *
 * Descoberto automaticamente pelo middleware AdminSidebarMenu.
 */
class DataController extends Controller
{
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_memcofre_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'memcofre_module');

        if (! $is_memcofre_enabled) {
            return;
        }

        if (! (auth()->user()->can('superadmin') || auth()->user()->can('memcofre.access') || auth()->user()->can('memcofre.admin'))) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->dropdown(
                __('memcofre::memcofre.docvault'),
                function ($sub) {
                    $sub->url('/docs',                __('memcofre::memcofre.dashboard'),  ['icon' => 'fa fas fa-chart-line']);
                    $sub->url('/memcofre/ingest',         __('memcofre::memcofre.ingest'),     ['icon' => 'fa fas fa-upload']);
                    $sub->url('/memcofre/inbox',          __('memcofre::memcofre.inbox'),      ['icon' => 'fa fas fa-inbox']);
                },
                [
                    'icon'   => 'fa fas fa-folder-open',
                    'active' => request()->segment(1) == 'memcofre',
                ]
            )->order(95);
        });
    }

    public function superadmin_package()
    {
        return [
            [
                'name'    => 'memcofre_module',
                'label'   => __('memcofre::memcofre.module_label'),
                'default' => false,
            ],
        ];
    }

    public function user_permissions()
    {
        return [
            [
                'value'   => 'memcofre.access',
                'label'   => __('memcofre::memcofre.permissao_acesso'),
                'default' => false,
            ],
            [
                'value'   => 'memcofre.admin',
                'label'   => __('memcofre::memcofre.permissao_admin'),
                'default' => false,
            ],
        ];
    }
}
