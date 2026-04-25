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
     * 3 tiers: Free / Pro R$ 199 / Enterprise R$ 599 (ver requisitos/Financeiro/README.md).
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

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('financeiro.access');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#ffd6a5' : '';
        $segmento_ativo = request()->segment(1) == 'financeiro';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    __('financeiro::financeiro.module_label'),
                    function ($sub) {
                        $sub->url(
                            url('/financeiro'),
                            __('financeiro::financeiro.menu.dashboard'),
                            [
                                'icon'   => 'fa fas fa-chart-pie',
                                'active' => request()->segment(1) == 'financeiro' && ! request()->segment(2),
                            ]
                        );

                        if (auth()->user()->can('superadmin') || auth()->user()->can('financeiro.contas_receber.view')) {
                            $sub->url(
                                url('/financeiro/contas-receber'),
                                __('financeiro::financeiro.menu.contas_receber'),
                                [
                                    'icon'   => 'fa fas fa-arrow-down',
                                    'active' => request()->segment(2) == 'contas-receber',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('financeiro.contas_pagar.view')) {
                            $sub->url(
                                url('/financeiro/contas-pagar'),
                                __('financeiro::financeiro.menu.contas_pagar'),
                                [
                                    'icon'   => 'fa fas fa-arrow-up',
                                    'active' => request()->segment(2) == 'contas-pagar',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('financeiro.caixa.view')) {
                            $sub->url(
                                url('/financeiro/caixa'),
                                __('financeiro::financeiro.menu.caixa'),
                                [
                                    'icon'   => 'fa fas fa-cash-register',
                                    'active' => request()->segment(2) == 'caixa',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('financeiro.contas_bancarias.manage')) {
                            $sub->url(
                                url('/financeiro/contas-bancarias'),
                                __('financeiro::financeiro.menu.contas_bancarias'),
                                [
                                    'icon'   => 'fa fas fa-university',
                                    'active' => request()->segment(2) == 'contas-bancarias',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('financeiro.conciliacao.manage')) {
                            $sub->url(
                                url('/financeiro/conciliacao'),
                                __('financeiro::financeiro.menu.conciliacao'),
                                [
                                    'icon'   => 'fa fas fa-check-double',
                                    'active' => request()->segment(2) == 'conciliacao',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('financeiro.relatorios.view')) {
                            $sub->url(
                                url('/financeiro/relatorios'),
                                __('financeiro::financeiro.menu.relatorios'),
                                [
                                    'icon'   => 'fa fas fa-chart-bar',
                                    'active' => request()->segment(2) == 'relatorios',
                                ]
                            );
                        }
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
