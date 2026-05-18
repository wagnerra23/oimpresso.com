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

        // Wagner 2026-05-18: esconder pra biz=4 (ROTA LIVRE Larissa). Ela não
        // usa governance (audit log + module grade gate são features dev/ops).
        $business_id = (int) session('user.business_id');
        if ($business_id === 4) return;

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                action(['\\Modules\\Governance\\Http\\Controllers\\DashboardController', 'index']),
                __('governance::governance.governance'),
                ['icon' => 'fa fa-shield', 'active' => request()->is('governance*')]
            )->order(199);
        });
    }
}
