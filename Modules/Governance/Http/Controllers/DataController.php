<?php

namespace Modules\Governance\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Governance — descoberto via AdminSidebarMenu.
 *
 * ADR 0086 MVP — UI única `/governance` (dashboard consolidado).
 */
class DataController extends Controller
{
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'governance_module',
                'label'   => __('governance::governance.governance'),
                'default' => false,
            ],
        ];
    }

    public function user_permissions()
    {
        return [
            [
                'value'   => 'governance.dashboard.view',
                'label'   => 'Ver painel de Governança',
                'default' => false,
            ],
            [
                'value'   => 'governance.policies.edit',
                'label'   => 'Editar policies (mcp_governance_rules)',
                'default' => false,
            ],
            [
                'value'   => 'governance.audit.view',
                'label'   => 'Ver audit log',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        if (!auth()->check()) return;

        $user = auth()->user();
        if (!$user->can('governance.dashboard.view')) return;

        // Visibilidade per-business é via subscription package
        // (Modules/Superadmin/PackagesController). NUNCA hardcode biz=N.

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                action(['\\Modules\\Governance\\Http\\Controllers\\DashboardController', 'index']),
                __('governance::governance.governance'),
                ['icon' => 'fa fa-shield', 'active' => request()->is('governance*')]
            )->order(199);
        });
    }
}
