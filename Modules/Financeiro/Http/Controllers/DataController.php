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

        // Sidebar: Wagner 2026-05-20 expandiu pra 10 entradas top-level ordenadas
        // por jornada (diário → mensal → config) — UX Larissa ROTA LIVRE não
        // precisa clicar pra descobrir Conciliação / Plano de Contas / Categorias
        // / Contador (antes invisíveis no sidebar mesmo já entregues nas
        // Ondas 18/19/31). Wave DRE 2026-05-20 (PRs #1266/1272/1276/1269/1277/1278)
        // separou em tela dedicada `/financeiro/dre` + legacy `/financeiro/relatorios`.
        //
        // Ordem canônica:
        //   85.0  Financeiro (unificada)   — diário
        //   85.1  Fluxo de Caixa           — diário
        //   85.2  Cobrança                 — diário/semanal
        //   85.3  Conciliação              — mensal
        //   85.4  DRE                      — mensal
        //   85.5  Relatórios               — mensal (legacy, cleanup PR D)
        //   85.6  Contas Bancárias         — config
        //   85.7  Plano de Contas          — config
        //   85.8  Categorias               — config
        //   85.9  Contador (Advisor)       — config
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
                // 1. Financeiro · Visão unificada (entrada principal)
                $menu->url(
                    url('/financeiro/unificado'),
                    __('financeiro::financeiro.module_label'),
                    [
                        'icon'   => 'fa fas fa-coins',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo && (request()->segment(2) == 'unificado' || request()->segment(2) == null),
                    ]
                )->order(85);

                // 2. Fluxo de Caixa
                $menu->url(
                    url('/financeiro/fluxo'),
                    __('financeiro::financeiro.cashflow_label'),
                    [
                        'icon'   => 'fa fas fa-chart-line',
                        'active' => $segmento_ativo && request()->segment(2) == 'fluxo',
                    ]
                )->order(85.1);

                // 3. Cobrança (substitui "Boletos" / "Gateway de Pagamento" —
                // Wagner 2026-05-19: F3 PaymentGateway UI Tela 1 entregue
                // em /financeiro/cobranca, escopo expandido pra todos tipos
                // boleto+pix+pix_recv+card. ADR 0144 + 0170).
                $menu->url(
                    url('/financeiro/cobranca'),
                    __('financeiro::financeiro.cobranca_label'),
                    [
                        'icon'   => 'fa fas fa-credit-card',
                        'active' => $segmento_ativo && request()->segment(2) === 'cobranca',
                        'group'  => 'fin',
                    ]
                )->order(85.2);

                // 4. Conciliação OFX (Onda 19 #49 entregue 2026-05-19 — antes
                // descoberta só via deeplink). Tarefa mensal de alto valor.
                $menu->url(
                    url('/financeiro/conciliacao'),
                    'Conciliação',
                    [
                        'icon'   => 'fa fas fa-exchange-alt',
                        'active' => $segmento_ativo && request()->segment(2) === 'conciliacao',
                        'group'  => 'fin',
                    ]
                )->order(85.3);

                // 5. DRE gerencial — Wave DRE 2026-05-20 PR C (#1272) reaplicação canon.
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
                    ]
                )->order(85.4);

                // 6. Relatórios (resumo + fluxo agregado) — entrada legada.
                // Mantida até PR D (cleanup tab DRE de Relatorios/Index.tsx);
                // avaliar absorção em Dashboard depois.
                $menu->url(
                    url('/financeiro/relatorios'),
                    __('financeiro::financeiro.relatorios_label'),
                    [
                        'icon'   => 'fa fas fa-chart-pie',
                        'active' => $segmento_ativo && request()->segment(2) == 'relatorios',
                    ]
                )->order(85.5);

                // 7. Contas Bancárias — Wagner 2026-05-19 reportou que /financeiro
                // não permitia cadastrar conta + vincular credencial gateway.
                // Página Inertia já existia (Modules/Financeiro/.../ContaBancariaController
                // → Pages/Financeiro/ContasBancarias/Index.tsx) só faltava
                // entrada no sidebar. Bloqueia Onda 5 dogfooding sem isso.
                $menu->url(
                    url('/financeiro/contas-bancarias'),
                    'Contas Bancárias',
                    [
                        'icon'   => 'fa fas fa-university',
                        'active' => $segmento_ativo && request()->segment(2) === 'contas-bancarias',
                        'group'  => 'fin',
                    ]
                )->order(85.6);

                // 8. Plano de Contas (Onda 18 #48 entregue 2026-05-19 — antes
                // só acessível via botão dentro de /unificado).
                $menu->url(
                    url('/financeiro/plano-contas'),
                    'Plano de Contas',
                    [
                        'icon'   => 'fa fas fa-sitemap',
                        'active' => $segmento_ativo && request()->segment(2) === 'plano-contas',
                        'group'  => 'fin',
                    ]
                )->order(85.7);

                // 9. Categorias — CRUD livre complementar ao Plano de Contas.
                $menu->url(
                    url('/financeiro/categorias'),
                    'Categorias',
                    [
                        'icon'   => 'fa fas fa-tags',
                        'active' => $segmento_ativo && request()->segment(2) === 'categorias',
                        'group'  => 'fin',
                    ]
                )->order(85.8);

                // 10. Contador (Portal Advisor) — Onda 31 #57 US-FIN-037 MVP
                // 2026-05-20. Owner concede acesso somente-leitura ao contador
                // parceiro (CNPJ + LGPD consent + escopo Unificado/Relatórios).
                // Permission gate `financeiro.advisor.grant` enforce no
                // AdvisorAccessController; sidebar não filtra por permissão
                // (pattern consistente com demais entradas — gate global
                // `financeiro.access` na linha 90 cobre o módulo).
                $menu->url(
                    url('/financeiro/configuracoes/contador'),
                    'Contador',
                    [
                        'icon'   => 'fa fas fa-user-tie',
                        'active' => $segmento_ativo
                            && request()->segment(2) === 'configuracoes'
                            && request()->segment(3) === 'contador',
                        'group'  => 'fin',
                    ]
                )->order(85.9);
            }
        );
    }
}
