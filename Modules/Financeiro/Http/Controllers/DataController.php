<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Financeiro.
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\<Nome>\Http\Controllers\DataController).
 *
 * Ver pattern: Modules/PontoWr2/Http/Controllers/DataController.php
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para painel Superadmin > Packages.
     * 3 tiers: Free / Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] (ver requisitos/Financeiro/README.md).
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'financeiro_module',
                'label'   => __('financeiro::financeiro.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no cadastro de Roles do UltimatePOS.
     * 13 permissões alinhadas com SPEC.md US-FIN-001 .. US-FIN-013.
     */
    public function user_permissions(): array
    {
        return [
            ['value' => 'financeiro.access',                     'label' => __('financeiro::financeiro.permissao_acesso'),                     'default' => false],
            ['value' => 'financeiro.dashboard.view',             'label' => __('financeiro::financeiro.permissao_dashboard_view'),             'default' => false],
            ['value' => 'financeiro.contas_receber.view',        'label' => __('financeiro::financeiro.permissao_contas_receber_view'),        'default' => false],
            ['value' => 'financeiro.contas_receber.create',      'label' => __('financeiro::financeiro.permissao_contas_receber_create'),      'default' => false],
            ['value' => 'financeiro.contas_receber.baixar',      'label' => __('financeiro::financeiro.permissao_contas_receber_baixar'),      'default' => false],
            ['value' => 'financeiro.contas_pagar.view',          'label' => __('financeiro::financeiro.permissao_contas_pagar_view'),          'default' => false],
            ['value' => 'financeiro.contas_pagar.create',        'label' => __('financeiro::financeiro.permissao_contas_pagar_create'),        'default' => false],
            ['value' => 'financeiro.contas_pagar.pagar',         'label' => __('financeiro::financeiro.permissao_contas_pagar_pagar'),         'default' => false],
            ['value' => 'financeiro.titulo.aprovar',             'label' => __('financeiro::financeiro.permissao_titulo_aprovar'),             'default' => false],
            ['value' => 'financeiro.caixa.view',                 'label' => __('financeiro::financeiro.permissao_caixa_view'),                 'default' => false],
            ['value' => 'financeiro.contas_bancarias.manage',    'label' => __('financeiro::financeiro.permissao_contas_bancarias_manage'),    'default' => false],
            ['value' => 'financeiro.conciliacao.manage',         'label' => __('financeiro::financeiro.permissao_conciliacao_manage'),         'default' => false],
            ['value' => 'financeiro.relatorios.view',            'label' => __('financeiro::financeiro.permissao_relatorios_view'),            'default' => false],
            ['value' => 'financeiro.relatorios.share',           'label' => __('financeiro::financeiro.permissao_relatorios_share'),           'default' => false],
            ['value' => 'financeiro.extrato.view',               'label' => __('financeiro::financeiro.permissao_extrato_view'),               'default' => false],
            // Onda 31 (2026-05-20) #57 US-FIN-037 — Portal Advisor contadores parceiros.
            ['value' => 'financeiro.advisor.grant',              'label' => __('financeiro::financeiro.permissao_advisor_grant'),              'default' => false],
        ];
    }

    /**
     * Injeta menu na sidebar admin. Order=85 (antes do PontoWr2 order=88).
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Financeiro');
        } else {
            $business_id = session('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'financeiro_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        // Wagner 2026-05-18: removido gate SUPERADMIN-ONLY (era "em desenvolvimento"
        // 2026-04-25). Após Onda 7 KB-9.75 (Curadoria+IA+CrossLink) módulo está
        // piloto-ready. Visibilidade agora controlada pelo pacote subscription
        // do business (configurável via Modules/Superadmin/PackagesController) +
        // permissão de usuário `financeiro.access` — pattern UltimatePOS canon
        // (NÃO hardcode biz=N — Wagner 2026-05-18: "habilitar é compra de
        // pacote no modulo superadmin").
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('financeiro.access')) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#ffd6a5' : '';
        $segmento_ativo = request()->segment(1) == 'financeiro';

        // Sidebar: Wagner 2026-05-20 evoluiu pra 12 entradas em 3 SUB-GRUPOS
        // visuais (Opção B "melhor usabilidade"). Cada entrada declara o
        // sub-grupo via `'group' => 'fin-op|fin-analise|fin-config'`.
        // SIDEBAR_GROUPS no frontend (Sidebar.tsx) renderiza 3 cabeçalhos
        // colapsáveis separados — Operação abre por default (uso diário).
        //
        // Ordem canônica (12 entradas):
        //   FINANCEIRO · OPERAÇÃO (aberto por default — diário)
        //     85.00  Visão Unificada       /financeiro/unificado
        //     85.10  Contas a Receber      /financeiro/contas-receber       ← novo no sidebar
        //     85.20  Contas a Pagar        /financeiro/contas-pagar         ← novo no sidebar
        //     85.30  Fluxo de Caixa        /financeiro/fluxo
        //     85.40  Cobrança              /financeiro/cobranca
        //     85.45  Caixa do turno        /financeiro/caixa (Fase 6 Soft)
        //   FINANCEIRO · ANÁLISE (fechado — mensal)
        //     85.50  Conciliação           /financeiro/conciliacao
        //     85.60  DRE                   /financeiro/dre
        //     85.70  Relatórios            /financeiro/relatorios (legacy, cleanup PR D)
        //   FINANCEIRO · AJUSTES (fechado — config)
        //     85.80  Contas Bancárias      /financeiro/contas-bancarias
        //     85.85  Plano de Contas       /financeiro/plano-contas
        //     85.90  Categorias            /financeiro/categorias
        //     85.95  Contador (Advisor)    /financeiro/configuracoes/contador
        //
        // Permission gates permanecem no gate global `financeiro.access`
        // (linha 90) — sidebar só esconde a entrada se módulo desligado.
        // Itens NOVOS declaram 'group' => 'fin' (Wagner regra 2026-05-19:
        // nunca hardcode label no SIDEBAR_GROUPS frontend, sempre via
        // DataController). LegacyMenuAdapter propaga essa key pro
        // ShellMenuItem.group, Sidebar.tsx findGroupKey usa antes do label match.
        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // ═══════════════════════════════════════════════════════════
                // SUB-GRUPO 1: FINANCEIRO · OPERAÇÃO (aberto por default)
                // Uso diário — Larissa @ ROTA LIVRE vê sem clicar.
                // ═══════════════════════════════════════════════════════════

                // Visão Unificada — cockpit AR+AP (entrada principal).
                //
                // ADR 0180 Fase 4 piloto Financeiro (2026-05-21): entry principal
                // declara atributos extras que LegacyMenuAdapter propaga pro frontend:
                //  - `shortcut` G F → atalho kbd canônico (overlay visual em Fase 8)
                //  - `primary`     → botão "+ Novo título" colorido (PageHeaderTabs)
                //  - `ghosts`      → 13 tabs ARIA da tela /financeiro/unificado
                //
                // As outras 12 entries continuam exibidas no sidebar (Wagner Opção B
                // 2026-05-20 — sub-grupos visuais Operação/Análise/Ajustes preservada).
                // Quando Fase 5 entregar PageHeaderTabs nas telas, a navegação
                // contextual entre sub-views vai espelhar este shape — sidebar pode
                // então enxugar pras 1 entry canon em PR futuro sem quebrar UX.
                //
                // hrefs dos ghosts usam path absoluto (url() seria interpretado pelo
                // LegacyMenuAdapter::toRelative). Espelha contrato
                // App\Sidebar\SidebarGhost (kebab-case key + label + path absoluto).
                $menu->url(
                    url('/financeiro/unificado'),
                    __('financeiro::financeiro.module_label'),
                    [
                        'icon'     => 'fa fas fa-coins',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo && (request()->segment(2) == 'unificado' || request()->segment(2) == null),
                        'group'    => 'fin-op',
                        'shortcut' => 'G F',
                        'primary'  => [
                            'label'    => 'Novo título',
                            'href'     => '/financeiro/lancamentos/create',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'unificado',         'label' => 'Unificado',         'href' => '/financeiro/unificado'],
                            ['key' => 'contas-receber',    'label' => 'Contas a Receber',  'href' => '/financeiro/contas-receber'],
                            ['key' => 'contas-pagar',      'label' => 'Contas a Pagar',    'href' => '/financeiro/contas-pagar'],
                            ['key' => 'fluxo',             'label' => 'Fluxo de Caixa',    'href' => '/financeiro/fluxo'],
                            ['key' => 'cobranca',          'label' => 'Cobrança',          'href' => '/financeiro/cobranca'],
                            ['key' => 'caixa',             'label' => 'Caixa do turno',    'href' => '/financeiro/caixa'],
                            ['key' => 'conciliacao',       'label' => 'Conciliação',       'href' => '/financeiro/conciliacao'],
                            ['key' => 'dre',               'label' => 'DRE',               'href' => '/financeiro/dre'],
                            ['key' => 'relatorios',        'label' => 'Relatórios',        'href' => '/financeiro/relatorios'],
                            ['key' => 'contas-bancarias',  'label' => 'Contas Bancárias',  'href' => '/financeiro/contas-bancarias'],
                            ['key' => 'plano-contas',      'label' => 'Plano de Contas',   'href' => '/financeiro/plano-contas'],
                            ['key' => 'categorias',        'label' => 'Categorias',        'href' => '/financeiro/categorias'],
                            ['key' => 'contador',          'label' => 'Contador',          'href' => '/financeiro/configuracoes/contador'],
                        ],
                    ]
                )->order(85.00);

                // Contas a Receber — Wagner 2026-05-20: cliente PME pede recorte
                // dedicado (mental model contábil BR). Tela já existia em
                // Pages/Financeiro/ContasReceber/Index.tsx, só faltava sidebar.
                $menu->url(
                    url('/financeiro/contas-receber'),
                    'Contas a Receber',
                    [
                        'icon'   => 'fa fas fa-hand-holding-usd',
                        'active' => $segmento_ativo && request()->segment(2) === 'contas-receber',
                        'group'  => 'fin-op',
                    ]
                )->order(85.10);

                // Contas a Pagar — idem (par CR/CP é padrão de mercado BR:
                // Conta Azul, Bling, Omie, Tiny, ContaSimples todos têm visíveis).
                $menu->url(
                    url('/financeiro/contas-pagar'),
                    'Contas a Pagar',
                    [
                        'icon'   => 'fa fas fa-money-bill-wave',
                        'active' => $segmento_ativo && request()->segment(2) === 'contas-pagar',
                        'group'  => 'fin-op',
                    ]
                )->order(85.20);

                // Fluxo de Caixa — projetado por vencimento dos títulos abertos.
                // (Cash Flow legacy do core UltimatePOS = realizado histórico,
                // será absorvido em Fase 3 com tabs Projetado/Realizado.)
                $menu->url(
                    url('/financeiro/fluxo'),
                    __('financeiro::financeiro.cashflow_label'),
                    [
                        'icon'   => 'fa fas fa-chart-line',
                        'active' => $segmento_ativo && request()->segment(2) == 'fluxo',
                        'group'  => 'fin-op',
                    ]
                )->order(85.30);

                // Cobrança — F3 PaymentGateway UI Tela 1 (ADR 0144 + 0170)
                // Wagner 2026-05-19: substitui "Boletos" / "Gateway de Pagamento"
                // legacy. Escopo expandido pra boleto+pix+pix_recv+card.
                $menu->url(
                    url('/financeiro/cobranca'),
                    __('financeiro::financeiro.cobranca_label'),
                    [
                        'icon'   => 'fa fas fa-credit-card',
                        'active' => $segmento_ativo && request()->segment(2) === 'cobranca',
                        'group'  => 'fin-op',
                    ]
                )->order(85.40);

                // Caixa do turno — Wagner 2026-05-21 Fase 6 Soft (wrapper Inertia).
                // Tela read-only sobre cash_registers core UltimatePOS; lifecycle
                // abrir/fechar continua na header POS. Larissa descobre histórico
                // de fechamentos sem precisar voltar à tela POS.
                // Permission gate `view_cash_register` enforce no CaixaController.
                if (auth()->user()->can('view_cash_register')) {
                    $menu->url(
                        url('/financeiro/caixa'),
                        'Caixa do turno',
                        [
                            'icon'   => 'fa fas fa-cash-register',
                            'active' => $segmento_ativo && request()->segment(2) === 'caixa',
                            'group'  => 'fin-op',
                        ]
                    )->order(85.45);
                }

                // ═══════════════════════════════════════════════════════════
                // SUB-GRUPO 2: FINANCEIRO · ANÁLISE (fechado por default)
                // Uso mensal — relatórios + reconciliação.
                // ═══════════════════════════════════════════════════════════

                // Conciliação OFX (Onda 19 #49 entregue 2026-05-19 — antes
                // descoberta só via deeplink). Tarefa mensal de alto valor.
                $menu->url(
                    url('/financeiro/conciliacao'),
                    'Conciliação',
                    [
                        'icon'   => 'fa fas fa-exchange-alt',
                        'active' => $segmento_ativo && request()->segment(2) === 'conciliacao',
                        'group'  => 'fin-analise',
                    ]
                )->order(85.50);

                // DRE gerencial — Wave DRE 2026-05-20 PR C (#1272) reaplicação canon.
                // Tela dedicada `/financeiro/dre` (TelaDRE hierárquica clássica:
                // Receita bruta → Deduções → Receita líquida → Custos → Lucro bruto
                // → Despesas → Resultado operacional). Substitui tab DRE da
                // antiga `/financeiro/relatorios` (que ficará só com Fluxo+Resumo,
                // cleanup em PR D).
                $menu->url(
                    url('/financeiro/dre'),
                    __('financeiro::financeiro.dre_label'),
                    [
                        'icon'   => 'fa fas fa-file-invoice-dollar',
                        'active' => $segmento_ativo && request()->segment(2) == 'dre',
                        'group'  => 'fin-analise',
                    ]
                )->order(85.60);

                // Relatórios (resumo + fluxo agregado) — entrada legada,
                // mantida até PR D (cleanup tab DRE de Relatorios/Index.tsx);
                // avaliar absorção em Dashboard depois.
                $menu->url(
                    url('/financeiro/relatorios'),
                    __('financeiro::financeiro.relatorios_label'),
                    [
                        'icon'   => 'fa fas fa-chart-pie',
                        'active' => $segmento_ativo && request()->segment(2) == 'relatorios',
                        'group'  => 'fin-analise',
                    ]
                )->order(85.70);

                // ═══════════════════════════════════════════════════════════
                // SUB-GRUPO 3: FINANCEIRO · AJUSTES (fechado por default)
                // Setup — usuário cadastra 1× e esquece.
                // ═══════════════════════════════════════════════════════════

                // Contas Bancárias — Wagner 2026-05-19 reportou que /financeiro
                // não permitia cadastrar conta + vincular credencial gateway.
                $menu->url(
                    url('/financeiro/contas-bancarias'),
                    'Contas Bancárias',
                    [
                        'icon'   => 'fa fas fa-university',
                        'active' => $segmento_ativo && request()->segment(2) === 'contas-bancarias',
                        'group'  => 'fin-config',
                    ]
                )->order(85.80);

                // Plano de Contas (Onda 18 #48 entregue 2026-05-19 — antes
                // só acessível via botão dentro de /unificado).
                $menu->url(
                    url('/financeiro/plano-contas'),
                    'Plano de Contas',
                    [
                        'icon'   => 'fa fas fa-sitemap',
                        'active' => $segmento_ativo && request()->segment(2) === 'plano-contas',
                        'group'  => 'fin-config',
                    ]
                )->order(85.85);

                // Categorias — CRUD livre complementar ao Plano de Contas.
                $menu->url(
                    url('/financeiro/categorias'),
                    'Categorias',
                    [
                        'icon'   => 'fa fas fa-tags',
                        'active' => $segmento_ativo && request()->segment(2) === 'categorias',
                        'group'  => 'fin-config',
                    ]
                )->order(85.90);

                // Contador (Portal Advisor) — Onda 31 #57 US-FIN-037 MVP
                // 2026-05-20. Owner concede acesso somente-leitura ao contador
                // parceiro (CNPJ + LGPD consent + escopo Unificado/Relatórios).
                // Permission gate `financeiro.advisor.grant` enforce no
                // AdvisorAccessController; sidebar não filtra por permissão
                // (pattern consistente — gate global `financeiro.access`
                // linha 90 cobre o módulo).
                $menu->url(
                    url('/financeiro/configuracoes/contador'),
                    'Contador',
                    [
                        'icon'   => 'fa fas fa-user-tie',
                        'active' => $segmento_ativo
                            && request()->segment(2) === 'configuracoes'
                            && request()->segment(3) === 'contador',
                        'group'  => 'fin-config',
                    ]
                )->order(85.95);
            }
        );
    }
}
