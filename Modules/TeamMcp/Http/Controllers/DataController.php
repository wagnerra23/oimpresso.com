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
                    'Forja',
                    function ($sub) {
                        // Fusão 2026-06-16 (Wagner: "não pode ficar duas concorrentes"):
                        // hub ÚNICO. Abas próprias da Forja + telas TeamMcp absorvidas.
                        $sub->url('/forja',           'Triagem',   ['icon' => 'fa fas fa-inbox',    'active' => request()->path() === 'forja']);
                        $sub->url('/forja/backlog',   'Backlog',   ['icon' => 'fa fas fa-list-ul',  'active' => request()->segment(2) === 'backlog']);
                        $sub->url('/forja/quadro',    'Quadro',    ['icon' => 'fa fas fa-columns',  'active' => request()->segment(2) === 'quadro']);
                        $sub->url('/forja/changelog', 'Changelog', ['icon' => 'fa fas fa-history',  'active' => request()->segment(2) === 'changelog']);
                        $sub->url('/forja/mcp',       'MCP',       ['icon' => 'fa fas fa-plug',     'active' => request()->segment(2) === 'mcp']);
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

                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.mcp.usage.all')) {
                            $sub->url(
                                route('team-mcp.scorecard.index'),
                                'Saúde',
                                [
                                    'icon'   => 'fa fas fa-heartbeat',
                                    'active' => request()->segment(2) == 'scorecard',
                                ]
                            );
                        }
                    },
                    [
                        'icon'     => 'fa fas fa-hammer',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => request()->segment(1) === 'forja' || $segmento_ativo,
                        'group'    => 'equipe',
                        'shortcut' => 'G F',
                        'ghosts'   => [
                            ['key' => 'triagem',     'label' => 'Triagem',     'href' => '/forja'],
                            ['key' => 'backlog-f',   'label' => 'Backlog',     'href' => '/forja/backlog'],
                            ['key' => 'quadro',      'label' => 'Quadro',      'href' => '/forja/quadro'],
                            ['key' => 'changelog',   'label' => 'Changelog',   'href' => '/forja/changelog'],
                            ['key' => 'mcp',         'label' => 'MCP',         'href' => '/forja/mcp'],
                            ['key' => 'scorecard',   'label' => 'Saúde',       'href' => '/team-mcp/scorecard'],
                            ['key' => 'team',        'label' => 'Equipe',      'href' => '/team-mcp/team'],
                            ['key' => 'tasks',       'label' => 'Tarefas',     'href' => '/team-mcp/tasks'],
                            ['key' => 'cc-sessions', 'label' => 'CC Sessions', 'href' => '/team-mcp/cc-sessions'],
                            // Wagner 2026-05-22 P0: ProjectMgmt absorvido como ghosts do hub Equipe.
                            // Zera 6 órfãs da matriz. PageHeaderTabs auto-overflow após 5 ghosts.
                            // 2026-05-29: + Triagem + Caixa de entrada (estavam só acessíveis
                            // por URL direta — sem entrada de navegação). hrefs single-prefix
                            // /project-mgmt/{triage,inbox} (NÃO dobrar prefixo). Ao lado de My Work.
                            ['key' => 'board',       'label' => 'Board',       'href' => '/project-mgmt/board'],
                            ['key' => 'my-work',     'label' => 'My Work',     'href' => '/project-mgmt/my-work'],
                            ['key' => 'triage',      'label' => 'Triagem (PM)',      'href' => '/project-mgmt/triage'],
                            ['key' => 'inbox',       'label' => 'Caixa de entrada',  'href' => '/project-mgmt/inbox'],
                            ['key' => 'backlog',     'label' => 'Backlog (PM)',      'href' => '/project-mgmt/backlog'],
                            ['key' => 'activity',    'label' => 'Activity',    'href' => '/project-mgmt/activity'],
                            ['key' => 'burndown',    'label' => 'Burndown',    'href' => '/project-mgmt/burndown'],
                            ['key' => 'roadmap',     'label' => 'Roadmap',     'href' => '/project-mgmt/roadmap'],
                        ],
                    ]
                )->order(91); // Logo após Copiloto (90) — hub único Forja (fusão 2026-06-16)
            }
        );
    }
}
