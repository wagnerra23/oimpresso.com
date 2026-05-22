<?php

namespace Modules\TeamMcp\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo TeamMcp.
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\TeamMcp\Http\Controllers\DataController@modifyAdminMenu).
 *
 * Espelha o topnav declarativo em Modules/TeamMcp/Resources/menus/topnav.php.
 *
 * IMPORTANTE: As permissões `copiloto.mcp.usage.all`, `copiloto.cc.read.team`
 * e `copiloto.cc.read.all` continuam vivendo na seed do Copiloto
 * (Modules/Copiloto/Database/Seeders/McpScopesSeeder.php) — não foram renomeadas
 * nesta etapa pra evitar quebrar usuários já configurados. Rename pra
 * `team-mcp.*` é tarefa futura (ADR pendente).
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para o painel Superadmin > Packages.
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'team_mcp_module',
                'label'   => __('teammcp::teammcp.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões expostas no cadastro de papéis (Roles) do UltimatePOS.
     *
     * NÃO declara permissões — as 3 telas reusam as permissions já existentes
     * do Copiloto (copiloto.mcp.usage.all, copiloto.cc.read.team). Rename
     * vira ADR + migration de permissões em etapa posterior.
     */
    public function user_permissions()
    {
        return [];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('TeamMcp');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'team_mcp_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('jana.mcp.usage.all')
            || auth()->user()->can('jana.cc.read.team');

        if (! $usuario_pode_ver) {
            return;
        }

        // Agrupamento visual em "IA & Produtividade" acontece no frontend
        // (SIDEBAR_GROUPS em resources/js/Components/cockpit/Sidebar.tsx).
        // DataController publica o dropdown padrão.
        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'team-mcp';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // ADR 0180 Fase 4 Wave C TOPO (2026-05-21): entry dropdown TeamMcp
                // declara atributos extras propagados pelo LegacyMenuAdapter pro
                // frontend Sidebar.tsx (v3 — grupo 'equipe' no TOPO):
                //  - `shortcut` G E → atalho kbd canônico (overlay Fase 8)
                //  - `ghosts`      → 3 sub-views (team/tasks/cc-sessions)
                //  - NÃO declara `primary` (módulo é read-only — observabilidade do
                //    uso MCP/CC pelo time, sem ação de criação primária).
                //
                // group: 'equipe' canon — LegacyMenuAdapter propaga pro Sidebar.tsx,
                // que renderiza TeamMcp no TOPO junto com ProjectMgmt (ghost de Equipe).
                //
                // Permission gates específicos (jana.mcp.usage.all, jana.cc.read.team)
                // permanecem enforce nos sub-itens — gate global hasThePermissionInSubscription
                // já cobre módulo on/off na entry principal.
                $menu->dropdown(
                    __('teammcp::teammcp.module_label'),
                    function ($sub) {
                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.mcp.usage.all')) {
                            $sub->url(
                                route('team-mcp.team.index'),
                                __('teammcp::teammcp.menu.team'),
                                [
                                    'icon'   => 'fa fas fa-users',
                                    'active' => request()->segment(2) == 'team',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.mcp.usage.all')) {
                            $sub->url(
                                route('team-mcp.tasks.index'),
                                __('teammcp::teammcp.menu.tasks'),
                                [
                                    'icon'   => 'fa fas fa-columns',
                                    'active' => request()->segment(2) == 'tasks',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.cc.read.team')) {
                            $sub->url(
                                route('team-mcp.cc.index'),
                                __('teammcp::teammcp.menu.cc_sessions'),
                                [
                                    'icon'   => 'fa fas fa-code',
                                    'active' => request()->segment(2) == 'cc-sessions',
                                ]
                            );
                        }
                    },
                    [
                        'icon'     => 'fa fas fa-users-cog',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo,
                        'group'    => 'equipe',
                        'shortcut' => 'G E',
                        'ghosts'   => [
                            ['key' => 'team',        'label' => 'Team',        'href' => '/team-mcp/team'],
                            ['key' => 'tasks',       'label' => 'Tasks',       'href' => '/team-mcp/tasks'],
                            ['key' => 'cc-sessions', 'label' => 'CC Sessions', 'href' => '/team-mcp/cc-sessions'],
                            // Wagner 2026-05-22 P0: ProjectMgmt absorvido como ghosts do hub Equipe.
                            // Zera 6 órfãs da matriz. PageHeaderTabs auto-overflow após 5 ghosts.
                            ['key' => 'board',       'label' => 'Board',       'href' => '/project-mgmt/board'],
                            ['key' => 'my-work',     'label' => 'My Work',     'href' => '/project-mgmt/my-work'],
                            ['key' => 'backlog',     'label' => 'Backlog',     'href' => '/project-mgmt/backlog'],
                            ['key' => 'activity',    'label' => 'Activity',    'href' => '/project-mgmt/activity'],
                            ['key' => 'burndown',    'label' => 'Burndown',    'href' => '/project-mgmt/burndown'],
                            ['key' => 'roadmap',     'label' => 'Roadmap',     'href' => '/project-mgmt/roadmap'],
                        ],
                    ]
                )->order(91); // Logo após Copiloto (90)
            }
        );
    }
}
