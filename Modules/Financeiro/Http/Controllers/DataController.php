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
            ['value' => 'financeiro.caixa.view',                 'label' => __('financeiro::financeiro.permissao_caixa_view'),                 'default' => false],
            ['value' => 'financeiro.contas_bancarias.manage',    'label' => __('financeiro::financeiro.permissao_contas_bancarias_manage'),    'default' => false],
            ['value' => 'financeiro.conciliacao.manage',         'label' => __('financeiro::financeiro.permissao_conciliacao_manage'),         'default' => false],
            ['value' => 'financeiro.relatorios.view',            'label' => __('financeiro::financeiro.permissao_relatorios_view'),            'default' => false],
            ['value' => 'financeiro.relatorios.share',           'label' => __('financeiro::financeiro.permissao_relatorios_share'),           'default' => false],
            ['value' => 'financeiro.extrato.view',               'label' => __('financeiro::financeiro.permissao_extrato_view'),               'default' => false],
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

        // Wagner 2026-05-18: liberar pra biz=4 (ROTA LIVRE Larissa, cliente piloto).
        // Pre-2026-05-18 era SUPERADMIN-ONLY (em desenvolvimento, pedido 2026-04-25).
        // Após Onda 7 KB-9.75 entregar Curadoria+IA+CrossLink, módulo está em
        // estado piloto-ready. Larissa vai usar; outros businesses (que ainda
        // não pediram explicitamente) seguem bloqueados pra evitar confusão.
        $business_id = (int) session('user.business_id');
        $piloto_rotalivre = ($business_id === 4);
        if (
            ! auth()->user()->can('superadmin')
            && ! $piloto_rotalivre
            && ! auth()->user()->can('financeiro.access')
        ) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#ffd6a5' : '';
        $segmento_ativo = request()->segment(1) == 'financeiro';

        // Sidebar: Wagner 2026-05-18 pediu sub-items DRE/Fluxo de Caixa/Boletos
        // visíveis pra ROTA LIVRE (cliente piloto vai usar fechamento mensal).
        // 4 entradas no dropdown — Visão unificada (default) + 3 sub-telas.
        // Permission gates permanecem nos controllers (sidebar só esconde entrada).
        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    __('financeiro::financeiro.module_label'),
                    function ($sub) {
                        $sub->url(
                            url('/financeiro/unificado'),
                            __('financeiro::financeiro.module_label'),
                            [
                                'icon'   => 'fa fas fa-coins',
                                'active' => request()->segment(2) == 'unificado' || request()->segment(2) == null,
                            ]
                        );
                        $sub->url(
                            url('/financeiro/fluxo'),
                            __('financeiro::financeiro.cashflow_label'),
                            [
                                'icon'   => 'fa fas fa-chart-line',
                                'active' => request()->segment(2) == 'fluxo',
                            ]
                        );
                        $sub->url(
                            url('/financeiro/relatorios/dre'),
                            __('financeiro::financeiro.dre_label'),
                            [
                                'icon'   => 'fa fas fa-file-invoice-dollar',
                                'active' => request()->segment(2) == 'relatorios' && request()->segment(3) == 'dre',
                            ]
                        );
                        $sub->url(
                            url('/financeiro/boletos'),
                            __('financeiro::financeiro.boletos_label'),
                            [
                                'icon'   => 'fa fas fa-barcode',
                                'active' => request()->segment(2) == 'boletos',
                            ]
                        );
                    },
                    [
                        'icon'   => 'fa fas fa-coins',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(85); // antes do PontoWr2 (88)
            }
        );
    }
}
