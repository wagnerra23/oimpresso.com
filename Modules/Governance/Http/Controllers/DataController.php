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
        // Wagner 2026-05-25: entry sidebar de Governança REMOVIDA — módulo
        // continua acessível por URL direta (/governance/dashboard, /policies,
        // /audit, /drift, /module-grades) mas não aparece no sidebar. Roteamento
        // canon agora é via Jana: login pós-redirect → /ia/dashboard (primeira
        // aba). Permissions/package/rotas preservadas — só desligamos o
        // Menu::modify pra parar de renderizar entry no AppShellV2.
        return;

        // ↓ DEAD CODE preservado pra histórico (até ADR formalizar a remoção).
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
            // ADR 0180 Fase 4 Wave E — entry principal Governance declara:
            //  - `shortcut` G G → atalho kbd canônico (overlay visual em Fase 8)
            //  - `primary`     → botão "Gerenciar policies" (PageHeaderTabs Fase 5)
            //  - `ghosts`      → 5 sub-views consolidadas (dashboard/policies/audit/drift/module-grades)
            //
            // ADR 0180 v3 — entry vive no grupo canon `sistema` (acoplado em Governança
            // visual no frontend). ADS/Auditoria/Cms/Connector/Officeimpresso são ghosts
            // virtuais agrupados pela Sidebar.tsx no mesmo cabeçalho `sistema`.
            // Wagner 2026-05-22 P2: hub Governance canon entry em SISTEMA + ghost
            // dashboard ajustado pra /governance/dashboard (legacy preservado), pq
            // /governance raiz virou redirect pra /ia (PR #1403).
            // URL principal volta a apontar /governance/dashboard pra evitar redirect.
            $menu->url(
                '/governance/dashboard',
                __('governance::governance.governance'),
                [
                    'icon'     => 'fa fa-shield',
                    'active'   => request()->is('governance*'),
                    'group'    => 'sistema',
                    'shortcut' => 'G G',
                    'primary'  => [
                        'label'    => 'Gerenciar policies',
                        'href'     => '/governance/policies',
                        'shortcut' => 'P',
                    ],
                    'ghosts'   => [
                        ['key' => 'dashboard',     'label' => 'Painel',          'href' => '/governance/dashboard'],
                        ['key' => 'policies',      'label' => 'Policies',        'href' => '/governance/policies'],
                        ['key' => 'audit',         'label' => 'Audit log',       'href' => '/governance/audit'],
                        ['key' => 'drift',         'label' => 'Drift alerts',    'href' => '/governance/drift'],
                        ['key' => 'module-grades', 'label' => 'Module Grades',   'href' => '/governance/module-grades'],
                    ],
                ]
            )->order(199);
        });
    }
}
