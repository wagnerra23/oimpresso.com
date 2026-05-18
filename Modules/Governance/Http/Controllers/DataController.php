<?php

namespace Modules\Governance\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Governance — descoberto via AdminSidebarMenu.
 *
 * ADR 0086 MVP — UI única `/governance` (dashboard consolidado).
 *
 * Visibilidade per-business via subscription package (`governance_module`
 * em `package_details`). Configurável via `/superadmin/packages` UI.
 * NUNCA hardcode `if ($business_id === N) return` — Wagner regra
 * IRREVOGÁVEL Tier 0 2026-05-18 (`memory/proibicoes.md`).
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

        $module_util = new ModuleUtil();

        // Gate 1: pacote subscription (configurável via UI Superadmin/Packages).
        // Superadmin: módulo instalado é suficiente (acesso total).
        // Usuário comum: depende do business ter `governance_module` ativo
        // no pacote — Wagner pode marcar/desmarcar via UI sem deploy code.
        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Governance');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'governance_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        // Gate 2: permission Spatie do usuário (role-based dentro do business).
        $user = auth()->user();
        if (!$user->can('governance.dashboard.view')) return;

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                action(['\\Modules\\Governance\\Http\\Controllers\\DashboardController', 'index']),
                __('governance::governance.governance'),
                ['icon' => 'fa fa-shield', 'active' => request()->is('governance*')]
            )->order(199);
        });
    }
}
