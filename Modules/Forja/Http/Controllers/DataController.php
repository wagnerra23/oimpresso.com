<?php

namespace Modules\Forja\Http\Controllers;

use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;
use Module;

/**
 * DataController do módulo Forja (chamado ProjectMgmt até 2026-07-30).
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\Forja\Http\Controllers\DataController@modifyAdminMenu).
 *
 * Espelha o topnav declarativo em Modules/Forja/Resources/menus/topnav.php.
 *
 * IMPORTANTE: as permissions reusam `jana.mcp.usage.all` (já existente)
 * — mesmo padrão do TeamMcp. Rename pra `project-mgmt.*` vira ADR + migration
 * em etapa futura.
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
                'name'    => 'project_mgmt_module',
                'label'   => 'Forja',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões expostas no cadastro de papéis (Roles) do UltimatePOS.
     *
     * As telas da Forja reusam `jana.mcp.usage.all` — não declaram permissão
     * própria.
     *
     * `brief.access` veio do ex-Modules/Brief (absorvido em 2026-07-30, ADR
     * 0091) e é declarada AQUI de propósito: ela é ENFORÇADA por
     * GenerateBriefRequest e ForceRefreshBriefRequest via `can('brief.access')`.
     * Sem este ponto de declaração o checkbox some de /roles/{id}/edit,
     * ninguém consegue conceder a permissão e os dois requests passam a negar
     * sempre. Habilitar/desabilitar é pela UI canônica, nunca por hardcode
     * (memory/proibicoes.md — Camada 3, Spatie por papel).
     *
     * O pacote `brief_module` NÃO foi trazido: nada o consumia
     * (`ModuleUtil::hasThePermissionInSubscription` não o referencia em lugar
     * nenhum) e a Forja já é gateada por `project_mgmt_module`.
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'brief.access',
                'label'   => 'Brief: acessar tool brief-fetch + admin',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            // FACHADA LEGACY (ADR 0088) — checagem explícita, de propósito.
            //
            // `ModuleUtil::isModuleInstalled($n)` assume que o nome nWidart e a chave
            // da row `system.{n}_version` coincidem. O rename ProjectMgmt→Forja
            // (2026-07-30) quebra essa assunção: o módulo é `Forja`, mas a row gravada
            // em produção — e ainda escrita por InstallController::moduleSystemKey() —
            // é `projectmgmt_version`. Nenhum argumento único serve: 'Forja' procuraria
            // `forja_version` (inexistente) e 'ProjectMgmt' falharia no Module::has().
            // Esta forma preserva o comportamento anterior ao rename.
            try {
                $is_enabled = Module::has('Forja')
                    && ! empty(System::getProperty('projectmgmt_version'));
            } catch (\Throwable $e) {
                // Fresh DB/CI: tabela `system` pode não existir antes do migrate —
                // mesmo tratamento do ModuleUtil original (não quebra o boot).
                $is_enabled = false;
            }
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'project_mgmt_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('jana.mcp.usage.all');

        if (! $usuario_pode_ver) {
            return;
        }

        // Wagner 2026-05-22 P0: Forja entry REMOVIDA — virou 6 ghosts
        // do hub Equipe (Modules/TeamMcp DataController). Zera 6 órfãs.
        // Tela /project-mgmt/* continua acessível via URL direta + ghost.
        return;

        // ↓ Código legacy preservado pra retomada futura se necessário ↓
        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'project-mgmt';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // ADR 0180 Fase 4 Wave C TOPO (2026-05-21): entry dropdown Forja
                // declara `group: 'equipe'` pro frontend Sidebar.tsx (v3) renderizar
                // Forja no TOPO junto com TeamMcp (ghost de Equipe).
                //
                // Forja NÃO declara shortcut próprio (instrução Wave C —
                // ghost de Equipe, atalho G E do TeamMcp cobre).
                // NÃO declara `primary` — primary canon do grupo Equipe é "Nova task"
                // mas vive em TeamMcp (tabela tasks). Quando module-grades elevar
                // Forja pra primário do grupo, mover primary aqui.
                //
                // Ghosts canônicos espelham as 6 sub-views do dropdown atual:
                //   my-work / board / backlog / roadmap / activity / burndown
                $menu->dropdown(
                    'Forja',
                    function ($sub) {
                        $sub->url(
                            route('project-mgmt.my-work.index'),
                            'My Work + Inbox',
                            [
                                'icon'   => 'fa fas fa-check-square',
                                'active' => request()->segment(2) == 'my-work',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.board.index'),
                            'Board (Kanban)',
                            [
                                'icon'   => 'fa fas fa-columns',
                                'active' => request()->segment(2) == 'board',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.backlog.index'),
                            'Backlog',
                            [
                                'icon'   => 'fa fas fa-list',
                                'active' => request()->segment(2) == 'backlog',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.roadmap.index'),
                            'Roadmap',
                            [
                                'icon'   => 'fa fas fa-calendar-alt',
                                'active' => request()->segment(2) == 'roadmap',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.activity.index'),
                            'Activity feed',
                            [
                                'icon'   => 'fa fas fa-stream',
                                'active' => request()->segment(2) == 'activity',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.burndown.index'),
                            'Burndown',
                            [
                                'icon'   => 'fa fas fa-chart-line',
                                'active' => request()->segment(2) == 'burndown',
                            ]
                        );
                    },
                    [
                        'icon'   => 'fa fas fa-project-diagram',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                        'group'  => 'equipe',
                        'ghosts' => [
                            ['key' => 'my-work',  'label' => 'My Work',  'href' => '/project-mgmt/my-work'],
                            ['key' => 'board',    'label' => 'Board',    'href' => '/project-mgmt/board'],
                            ['key' => 'backlog',  'label' => 'Backlog',  'href' => '/project-mgmt/backlog'],
                            ['key' => 'roadmap',  'label' => 'Roadmap',  'href' => '/project-mgmt/roadmap'],
                            ['key' => 'activity', 'label' => 'Activity', 'href' => '/project-mgmt/activity'],
                            ['key' => 'burndown', 'label' => 'Burndown', 'href' => '/project-mgmt/burndown'],
                        ],
                    ]
                )->order(92); // Logo após TeamMcp (91)
            }
        );

        // ------------------------------------------------------------------
        // Menu herdado do Modules/TeamMcp (apagado em 2026-07-31). Bloco
        // relocado COMO ESTAVA: os dois dropdowns 'Forja' ja coexistiam em
        // producao — este aponta /forja/* + /team-mcp/*, o de cima aponta
        // /project-mgmt/*. Fundir os dois e decisao [W] (deletaria uma das
        // implementacoes), separada desta deprecacao.
        //
        // Gate trocado de propósito: era isModuleInstalled('TeamMcp') +
        // 'team_mcp_module'. Medido em prod 2026-07-31: ZERO dos 70 pacotes
        // tinham team_mcp_module ligado, entao so o superadmin via este menu.
        // Passa a seguir o modulo Forja — mesma audiencia efetiva.
        // ------------------------------------------------------------------
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Forja');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'forja_module',
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
                                __('projectmgmt::projectmgmt.menu.team'),
                                [
                                    'icon'   => 'fa fas fa-users',
                                    'active' => request()->segment(2) == 'team',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.mcp.usage.all')) {
                            $sub->url(
                                route('team-mcp.tasks.index'),
                                __('projectmgmt::projectmgmt.menu.tasks'),
                                [
                                    'icon'   => 'fa fas fa-columns',
                                    'active' => request()->segment(2) == 'tasks',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.cc.read.team')) {
                            $sub->url(
                                route('team-mcp.cc.index'),
                                __('projectmgmt::projectmgmt.menu.cc_sessions'),
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
                            // Gantt recebido do Modules/Jana em 2026-08-05 (ADR 0366 §D-B +
                            // ADR 0367 D4). Key e label DIFEREM do ghost 'roadmap' acima de
                            // propósito: são duas leituras distintas do mesmo backlog e as
                            // DUAS ficam — o quarter view agrupa epics por trimestre, o Gantt
                            // agrupa tasks no tempo, e nenhum responde a pergunta do outro
                            // (o quarter não tem due_date/blocked_by; o Gantt não tem epic_id).
                            // A 0367 D7: o quarter view "só sai quando o Gantt provar que
                            // substitui". Key duplicada faria os dois disputarem o destaque
                            // ativo no PageHeaderTabs.
                            ['key' => 'roadmap-gantt', 'label' => 'Roadmap (Gantt)', 'href' => '/forja/roadmap-gantt'],
                        ],
                    ]
                )->order(91); // Logo após Copiloto (90) — hub único Forja (fusão 2026-06-16)
            }
        );
    }
}
