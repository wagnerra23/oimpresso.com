<?php

namespace Modules\Jana\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Copiloto.
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\Jana\Http\Controllers\DataController@modifyAdminMenu).
 *
 * Espelha o topnav declarativo em Modules/Copiloto/Resources/menus/topnav.php.
 *
 * IMPORTANTE: O módulo Copiloto ainda NÃO possui arquivos de tradução em
 * Modules/Copiloto/Resources/lang/. As chaves `copiloto::copiloto.*` usadas
 * abaixo serão resolvidas como literal pelo Laravel (fallback automático)
 * até que o lang file seja criado. Chaves esperadas:
 *  - copiloto::copiloto.module_label
 *  - copiloto::copiloto.permissao_acesso
 *  - copiloto::copiloto.permissao_chat
 *  - copiloto::copiloto.permissao_metas
 *  - copiloto::copiloto.permissao_superadmin
 *  - copiloto::copiloto.permissao_admin_custos
 *  - copiloto::copiloto.menu.conversar
 *  - copiloto::copiloto.menu.dashboard
 *  - copiloto::copiloto.menu.metas
 *  - copiloto::copiloto.menu.alertas
 *  - copiloto::copiloto.menu.plataforma
 *  - copiloto::copiloto.menu.custos
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para o painel Superadmin > Packages.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'copiloto_module',
                'label'   => __('copiloto::copiloto.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões expostas no cadastro de papéis (Roles) do UltimatePOS.
     * Espelha exatamente as permissões declaradas em
     * Modules/Copiloto/Resources/menus/topnav.php.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'jana.access',
                'label'   => __('copiloto::copiloto.permissao_acesso'),
                'default' => false,
            ],
            [
                'value'   => 'jana.chat',
                'label'   => __('copiloto::copiloto.permissao_chat'),
                'default' => false,
            ],
            [
                'value'   => 'jana.metas.manage',
                'label'   => __('copiloto::copiloto.permissao_metas'),
                'default' => false,
            ],
            [
                'value'   => 'jana.superadmin',
                'label'   => __('copiloto::copiloto.permissao_superadmin'),
                'default' => false,
            ],
            [
                'value'   => 'jana.admin.custos.view',
                'label'   => __('copiloto::copiloto.permissao_admin_custos'),
                'default' => false,
            ],
            ...$this->mcpScopePermissions(),
        ];
    }

    /**
     * Scopes MCP (`jana.mcp.*`) como checkbox na tela de roles.
     *
     * POR QUE EXISTE (incidente 2026-07-29, 4ª volta da mesma classe):
     * `RoleController@update` faz `syncPermissions($request->input('permissions'))`,
     * que é DESTRUTIVO — remove toda permission que não veio no POST. Como
     * nenhum módulo expunha os `jana.mcp.*` aqui, eles não viravam checkbox,
     * não iam no POST, e **qualquer save de qualquer role apagava a família
     * inteira**. Em 29/07 um save na role `Operacional#1` (biz=1) zerou os 17
     * scopes e derrubou o MCP dos 4 users do time (WR23, Felipe, Maiara, Luiz):
     * token válido + `403 no_permission` no gate `jana.mcp.use`.
     *
     * A prova de que era isso e não outra coisa: depois do save, o conjunto
     * `jana.*` da role era EXATAMENTE os 5 itens que este método devolvia.
     *
     * DERIVADO, não escrito à mão (ADR 0256): a lista vem do catálogo do
     * `McpScopesSeeder` — a mesma fonte que cria as Spatie permissions. Scope
     * novo no seeder aparece aqui sozinho. `McpScopesVisiveisNoRoleEditTest`
     * trava a paridade.
     *
     * `default => false` de propósito: aparecer na tela ≠ vir marcado. Quem
     * concede é o admin do business (Camada 3, multi-tenant).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mcpScopePermissions(): array
    {
        return array_map(
            static fn (array $scope): array => [
                'value'   => $scope['slug'],
                'label'   => 'MCP: '.$scope['nome'],
                'default' => false,
            ],
            \Modules\Jana\Database\Seeders\McpScopesSeeder::catalogo()
        );
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     *
     * Padrão UltimatePOS: o core chama este método a cada request via
     * middleware `AdminSidebarMenu`, e o item só aparece se o módulo estiver
     * habilitado para o business_id corrente (ou se o usuário for superadmin).
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Jana');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'copiloto_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        // Superadmin sempre vê; usuário comum precisa ao menos de copiloto.access.
        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('jana.access')
            || auth()->user()->can('jana.chat');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'jana';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // ADR 0180 Fase 4 Wave C TOPO (2026-05-21): entry dropdown principal
                // declara atributos extras propagados pelo LegacyMenuAdapter pro
                // frontend Sidebar.tsx (v3 — grupo 'ia' no TOPO):
                //  - `shortcut` G I → atalho kbd canônico (overlay Fase 8)
                //  - `primary`     → "Conversar com Jana" (entry-point IA do módulo)
                //  - `ghosts`      → 6 sub-views (conversar/dashboard/metas/alertas/custos/plataforma)
                //
                // group: 'ia' canon — LegacyMenuAdapter propaga pro Sidebar.tsx,
                // que renderiza Jana no TOPO junto com KB/Brief/SRS (ghosts de IA).
                //
                // hrefs absolutos (não usa route() helper aqui pra evitar interpretação
                // no LegacyMenuAdapter::toRelative). Permission gates específicos
                // (jana.chat, jana.metas.manage, jana.admin.custos.view, jana.superadmin)
                // permanecem enforce nos Controllers individuais — gate global
                // hasThePermissionInSubscription já cobre módulo on/off na entry.
                $menu->dropdown(
                    __('copiloto::copiloto.module_label'),
                    function ($sub) {
                        // Conversar — entry-point do módulo (chat IA)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.chat')) {
                            $sub->url(
                                route('jana.chat.index'),
                                __('copiloto::copiloto.menu.conversar'),
                                [
                                    'icon'   => 'fa fas fa-comments',
                                    'active' => request()->segment(1) == 'jana'
                                                && ! request()->segment(2),
                                ]
                            );
                        }

                        // Dashboard
                        $sub->url(
                            route('jana.dashboard.index'),
                            __('copiloto::copiloto.menu.dashboard'),
                            [
                                'icon'   => 'fa fas fa-tachometer-alt',
                                'active' => request()->segment(2) == 'dashboard',
                            ]
                        );

                        // Metas
                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.metas.manage')) {
                            $sub->url(
                                route('jana.metas.index'),
                                __('copiloto::copiloto.menu.metas'),
                                [
                                    'icon'   => 'fa fas fa-bullseye',
                                    'active' => request()->segment(2) == 'metas',
                                ]
                            );
                        }

                        // Alertas — REMOVIDO do dropdown legacy (Wagner 2026-05-25).
                        // Tela /ia/alertas é STUB ("spec-ready ver US-COPI-060") sem
                        // implementação real. Reativar quando US-COPI-060 entregar.
                        // Rota e Controller mantidos pra não quebrar bookmarks externos.

                        // Custos de IA MOVIDO pra Modules/Governance em 2026-08-05
                        // (ADR 0366 §D-B). Este bloco tinha que sair JUNTO com a rota:
                        // ele chamava `route('jana.admin.custos.index')`, e um nome de
                        // rota inexistente lança RouteNotFoundException — derrubaria o
                        // sidebar INTEIRO, não só este item. A entrada agora vive no
                        // ghost `custos` do DataController da Governança.

                        // Plataforma (superadmin-only)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.superadmin')) {
                            $sub->url(
                                route('jana.superadmin.metas'),
                                __('copiloto::copiloto.menu.plataforma'),
                                [
                                    'icon'   => 'fa fas fa-building',
                                    'active' => request()->segment(2) == 'superadmin',
                                ]
                            );
                        }
                    },
                    [
                        'icon'     => 'fa fas fa-compass',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo,
                        'group'    => 'ia',
                        'shortcut' => 'G I',
                        'primary'  => [
                            'label'    => 'Conversar com Jana',
                            'href'     => '/ia',
                            'shortcut' => 'N',
                        ],
                        // ADR 0182 + GUIA-SIDEBAR-V3 Wagner 2026-05-21: hub IA com
                        // sub-views canon do guia (Copiloto/Brief/Memórias/KB/Regras)
                        // + ghosts internos Jana (Dashboard/Metas/Custos).
                        // Labels CURTOS (≤2 palavras). PageHeaderTabs auto-promove ghost
                        // ativo inline mesmo se index >= maxVisible.
                        //
                        // Wagner 2026-05-22: hrefs /jana → /ia (vertical-slice IA piloto
                        // sidebar v3 — URL canon casa com label "IA" do topo).
                        'ghosts'   => [
                            // Wagner 2026-05-25: Dashboard PROMOVIDO pra primeira aba canon
                            // da Jana — destino pós-login (`/home → /ia/dashboard`). Charter
                            // Pages/Jana/Dashboard.charter.md já cobre empty state. Substitui
                            // Copiloto (chat) como entry-point default da Jana — chat continua
                            // acessível em 2ª aba e via FAB. Tentativas anteriores travaram em
                            // DashboardController@index redirect "sem metas → chat" (removido).
                            // ── Onda 2 da fusão (US-COPI-148, 2026-08-07) ──────────────
                            // Vocabulário das abas passa a ser `Painel | Conversa | Memória`,
                            // que é o do protótipo aprovado. Só o LABEL muda: as `key` são
                            // identificador interno, casadas com `activeGhostKey` do
                            // PageHeaderTabs e com o `mapActiveToGhostKey` do JanaAreaHeader —
                            // renomeá-las quebraria o match em silêncio (a aba simplesmente
                            // deixaria de acender) sem nenhum ganho visível. As `key` viram
                            // `painel`/`conversa` na onda 3, junto com o rename do arquivo.
                            ['key' => 'dashboard', 'label' => 'Painel',   'href' => '/ia/dashboard'],
                            ['key' => 'copiloto',  'label' => 'Conversa', 'href' => '/ia'],
                            // Ghost 'brief' removido 2026-06-15 (Wagner): /ia/brief era stub
                            // redundante (brief vive no chat + brief-fetch MCP + seção "Brief
                            // diário" do dashboard). Rota + BriefController + Page apagados.
                            // href passa a apontar pro destino REAL. `/ia/memorias` é
                            // `Route::redirect(…, '/ia/memoria', 302)` (routes.php:243) — item de
                            // menu que só redireciona é a "fronteira suja" que motivou a remoção
                            // do ghost `kb` neste mesmo bloco em 2026-08-05 (ADR 0366). A key
                            // `memorias` fica: é o alvo do `mapActiveToGhostKey('memoria')`.
                            ['key' => 'memorias',  'label' => 'Memória',  'href' => '/ia/memoria'],
                            // Ghost 'kb' removido 2026-08-05 ([W]: "governança, KB, saem"):
                            // `/ia/kb` é `Route::redirect(…, '/kb', 302)` — apontava pro
                            // redirect de uma tela que é do Modules/KB e tem entrada própria.
                            // Item de menu que só redireciona é fronteira suja (ADR 0366).
                            // Ghost 'regras' removido 2026-08-04 [W]: /ia/regras era stub de
                            // domínio ALHEIO — cobria policies do PolicyEngine ADS + governance
                            // MCP cross-team, e o núcleo do ADS foi pra Modules/Forja em jul/2026.
                            // Lia zero tabela; só apontava pra /ia/admin/governanca.
                            // Rota + RegrasController + Page + charter + scorecard apagados.
                            // Jana Pro — entry-point pro paywall/upgrade (ADR 0140). Ghost no hub IA
                            // pra ficar clicável de qualquer tela Jana (a própria /ia/pro é modo FOCO
                            // sem SubNav). Billing real fica pra Sprint JANA-B.
                            ['key' => 'pro',       'label' => 'Jana Pro',  'href' => '/ia/pro'],
                            // Wagner 2026-05-25: Governança canon (Modules/Governance · policies/audit/
                            // drift/module-grades) entra como ghost da Jana — "governança é da IA".
                            // Ghost 'governanca' removido 2026-08-05 ([W]: "governança, KB, saem").
                            // A justificativa original deste ghost era que a entry de sidebar da
                            // Governança estava DESLIGADA (early-return no modifyAdminMenu dela), então
                            // a Jana emprestava o acesso. Isso deixou de valer: o #5308 REATIVOU a
                            // entry, e a Governança ganhou faixa própria com as 7 sub-views. O ghost
                            // virou 2ª porta pro mesmo lugar — e o comentário que o defendia estava
                            // descrevendo um mundo que não existe mais desde o mesmo dia.
                            // Wagner 2026-05-23: ghost 'metas' removido — MetasController@index ainda
                            // retorna Blade view ('copiloto::metas.index'), o que faz Inertia Link no
                            // PageHeaderTabs silenciar (click no-op). Reintroduzir quando MetasController
                            // for migrado pra Inertia::render via MWART.
                            // Ghost 'custos' removido 2026-08-05 (ADR 0366 §D-B): a tela
                            // foi pra /governance/custos e agora é ghost da Governança.
                            // Ghost 'ads' REMOVIDO na parte 6 (ADR 0363): apontava pra
                            // /ads/admin/decisoes, tela do núcleo dual-brain que deixou de existir
                            // junto com o Modules/ADS. As telas que continuam sob /ads/ (projects,
                            // tools, team-scopes, graph) são da Forja e do KB, e a Forja tem entrada
                            // própria de sidebar no DataController dela — não ficam órfãs.
                            // Wagner 2026-05-25: promovidas pra ghosts após audit Jana
                            // (browser MCP smoke detectou 3 Pages órfãs sem link).
                            //  - cockpit: Jana V2 Analista IA (Brief + KPIs + análises) — Pages/Jana/Cockpit.tsx
                            //  - roadmap: Timeline Gantt das tasks MCP — Pages/Jana/Admin/Roadmap.tsx
                            // Painel.tsx fica acessível só por URL (mock Onda A1, sobreposto ao Cockpit).
                            ['key' => 'cockpit',  'label' => 'Cockpit',  'href' => '/ia/cockpit'],
                            // Ghost 'roadmap' removido 2026-08-05 (ADR 0366 §D-B + 0367 D4):
                            // o Gantt virou aba da Forja (/forja/roadmap-gantt). Tasks é Forja.
                            // Ghost 'qualidade-jana' removido 2026-08-05 (ADR 0366 §D-B):
                            // eval é gate de conformidade, foi pra /governance/qualidade-ia.
                            // Ghost 'governanca-mcp' removido 2026-08-05: a tela foi FUNDIDA
                            // no /governance/dashboard (ADR 0366 §D-C item 1). O ghost
                            // 'governanca' logo acima já aponta pra lá — manter os dois
                            // seria duas entradas pro mesmo destino.
                            //
                            // ⚠️ 2026-08-05 (2ª passada, [W] viu em PRODUÇÃO): os dois comentários
                            // acima chegaram no main SEM as remoções que anunciavam. #5312 tirou o
                            // 'governanca-mcp' e #5309 tirou o 'qualidade-jana', em hunks distintos
                            // do MESMO bloco — o git mergeou os dois comentários e preservou a linha
                            // que o outro PR apagava. Comentário dizia "removido", menu mostrava os
                            // dois. Mesma família do `drift_alerts` duplicado no SCOPE.md (#5328):
                            // merge paralelo no mesmo bloco não gera conflito e não valida o
                            // resultado. Agora as linhas saíram DE FATO — confira pelo menu, não
                            // por este comentário.
                        ],
                    ]
                )->order(90); // Logo após PontoWr2 (88)
            }
        );
    }
}
