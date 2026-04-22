<?php

namespace Modules\DocVault\Http\Controllers;

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
        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->dropdown(
                __('docvault::docvault.docvault'),
                function ($sub) {
                    $sub->url('/docs',                __('docvault::docvault.dashboard'),  ['icon' => 'fa fas fa-chart-line']);
                    $sub->url('/docs/ingest',         __('docvault::docvault.ingest'),     ['icon' => 'fa fas fa-upload']);
                    $sub->url('/docs/inbox',          __('docvault::docvault.inbox'),      ['icon' => 'fa fas fa-inbox']);
                },
                [
                    'icon'   => 'fa fas fa-folder-open',
                    'active' => request()->segment(1) == 'docs',
                ]
            )->order(95);
        });
    }

    public function superadmin_package()
    {
        return [
            [
                'name'    => 'docvault_module',
                'label'   => __('docvault::docvault.module_label'),
                'default' => false,
            ],
        ];
    }

    public function user_permissions()
    {
        return [
            [
                'value'   => 'docvault.access',
                'label'   => __('docvault::docvault.permissao_acesso'),
                'default' => false,
            ],
            [
                'value'   => 'docvault.admin',
                'label'   => __('docvault::docvault.permissao_admin'),
                'default' => false,
            ],
        ];
    }
}
