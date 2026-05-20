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

        // Sidebar: Wagner 2026-05-18 pediu 4 entradas SEPARADAS top-level (não
        // dropdown popover-2). UX mais visível pra Larissa ROTA LIVRE — não
        // precisa clicar pra ver Fluxo/DRE/Boletos. Cada item ocupa linha
        // própria no grupo FINANCEIRO (SIDEBAR_GROUPS em Sidebar.tsx mapeia
        // as 4 labels pro mesmo grupo visual).
        // Permission gates permanecem nos controllers (sidebar só esconde entrada).
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

                // 3. DRE gerencial — Wagner 2026-05-20 PR C reaplicação canon.
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
                )->order(85.2);

                // 3.1. Relatórios (resumo + fluxo agregado) — entrada legada.
                // Mantida até PR D (cleanup tab DRE de Relatorios/Index.tsx);
                // avaliar absorção em Dashboard depois.
                $menu->url(
                    url('/financeiro/relatorios'),
                    __('financeiro::financeiro.relatorios_label'),
                    [
                        'icon'   => 'fa fas fa-chart-pie',
                        'active' => $segmento_ativo && request()->segment(2) == 'relatorios',
                    ]
                )->order(85.25);

                // 4. Cobrança (substitui "Boletos" / "Gateway de Pagamento" —
                // Wagner 2026-05-19: F3 PaymentGateway UI Tela 1 entregue
                // em /financeiro/cobranca, escopo expandido pra todos tipos
                // boleto+pix+pix_recv+card. ADR 0144 + 0170).
                //
                // 'group' => 'fin' declara o grupo sidebar — Wagner regra
                // 2026-05-19: "nunca hardcode label no SIDEBAR_GROUPS frontend,
                // sempre via DataController do módulo". LegacyMenuAdapter
                // propaga essa key pro ShellMenuItem.group, Sidebar.tsx
                // findGroupKey usa antes do label match.
                $menu->url(
                    url('/financeiro/cobranca'),
                    __('financeiro::financeiro.cobranca_label'),
                    [
                        'icon'   => 'fa fas fa-credit-card',
                        'active' => $segmento_ativo && request()->segment(2) === 'cobranca',
                        'group'  => 'fin',
                    ]
                )->order(85.3);

                // 5. Contas Bancárias — Wagner 2026-05-19 reportou que /financeiro
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
                )->order(85.5);
            }
        );
    }
}
