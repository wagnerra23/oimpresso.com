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

        // Wagner 2026-05-22 P0: a entry da Forja saiu DAQUI — virou ghost do
        // hub Equipe. Este `return` e INCONDICIONAL: tudo abaixo dele nunca
        // executa, o dropdown de /forja + /team-mcp inclusive. Se ele volta ou
        // nao, e a fusao dos dois menus — decisao [W], fora da Onda 11.
        //
        // Onda 11 (2026-09-02): o dropdown que apontava /project-mgmt/* foi
        // APAGADO, nao apenas desativado. Ele chamava route('project-mgmt.
        // board.index') e 5 irmas — nomes que a revogacao removeu. Deixa-lo
        // ali seria plantar RouteNotFoundException pro dia em que alguem
        // tirasse este return.
        return;

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
                            // Onda 11 (2026-09-02): os 7 ghosts de /project-mgmt/* sairam
                            // com as telas (ADR 0367 D1 + PARIDADE §11). Board e Backlog
                            // sao hoje segmentos de /forja/trabalho; a Triagem e aba do
                            // hub; My Work, Caixa de entrada, Activity e Burndown foram
                            // perda consciente (D5/D1). Fica so o quarter view, que a D7
                            // preserva ate o Gantt provar que substitui.
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
