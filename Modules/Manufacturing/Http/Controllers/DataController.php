<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;
use Modules\Manufacturing\Utils\ManufacturingUtil;

class DataController extends Controller
{
    /**
     * Defines module as a superadmin package.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'manufacturing_module',
                'label' => __('manufacturing::lang.manufacturing_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Defines user permissions for the module.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'manufacturing.access_recipe',
                'label' => __('manufacturing::lang.access_recipe'),
                'default' => false,
            ],
            [
                'value' => 'manufacturing.add_recipe',
                'label' => __('manufacturing::lang.add_recipe'),
                'default' => false,
            ],
            [
                'value' => 'manufacturing.edit_recipe',
                'label' => __('manufacturing::lang.edit_recipe'),
                'default' => false,
            ],
            [
                'value' => 'manufacturing.access_production',
                'label' => __('manufacturing::lang.access_production'),
                'default' => false,
            ],
        ];
    }

    /**
     * Adds Manufacturing menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        // Do container (não `new`): mesmo objeto em produção, e o teste do menu consegue fixar o pacote.
        $module_util = app(ModuleUtil::class);
        $is_mfg_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'manufacturing_module', 'superadmin_package');

        $pode_receita = auth()->user()->can('manufacturing.access_recipe');
        $pode_producao = auth()->user()->can('manufacturing.access_production');

        if ($is_mfg_enabled && ($pode_receita || $pode_producao)) {
            // UC-RECIPE-24 (lote 2.4 da auditoria): cada item leva a uma rota que o usuário abre.
            // Receitas e Insumos pedem `access_recipe`; Ordens, Relatório e Configurações pedem
            // `access_production` (decisão B3 de [W], 2026-11-09). Antes os cinco apareciam para
            // quem tinha uma das duas, e o item principal ia a Receitas mesmo para quem só produz.
            $ghosts = [];
            if ($pode_receita) {
                $ghosts[] = ['key' => 'recipe',   'label' => 'Receitas', 'href' => '/manufacturing/recipe'];
                $ghosts[] = ['key' => 'insumos',  'label' => 'Insumos',  'href' => '/manufacturing/insumos'];
            }
            if ($pode_producao) {
                $ghosts[] = ['key' => 'production', 'label' => 'Produções',     'href' => '/manufacturing/production'];
                $ghosts[] = ['key' => 'report',     'label' => 'Relatório',     'href' => '/manufacturing/report'];
                $ghosts[] = ['key' => 'settings',   'label' => 'Configurações', 'href' => '/manufacturing/settings'];
            }
            $principal = $pode_receita
                ? action([\Modules\Manufacturing\Http\Controllers\RecipeController::class, 'index'])
                : action([\Modules\Manufacturing\Http\Controllers\ProductionController::class, 'index']);

            Menu::modify('admin-sidebar-menu', function ($menu) use ($ghosts, $principal, $pode_producao) {
                // ADR 0180 Fase 4 Wave B (2026-05-21): entry principal declara
                // atalho kbd + primary action + ghosts tabs.
                //  - `shortcut` G P → atalho overlay (Produção)
                //  - `primary`     → "Nova ordem de produção" via ProductionController@create
                //  - `ghosts`      → Receitas, Produções, Relatório, Configurações
                $atributos = [
                    'icon'     => 'fa fas fa-industry',
                    'style'    => config('app.env') == 'demo' ? 'background-color: #ff851b;' : '',
                    'active'   => request()->segment(1) == 'manufacturing',
                    'shortcut' => 'G P',
                    'ghosts'   => $ghosts,
                ];
                // "Nova ordem" abre o formulário de ordem, que pede `access_production`.
                // Destino é a Blade até a etapa 6 da ADR 0448 chegar em produção (§6: a Blade
                // sai na etapa 7). Mesmo destino de `resources/js/Pages/Manufacturing/_lib/ordem.ts`.
                if ($pode_producao) {
                    $atributos['primary'] = [
                        'label'    => 'Nova ordem de produção',
                        'href'     => '/manufacturing/production/create',
                        'shortcut' => 'N',
                    ];
                }

                $menu->url($principal, __('manufacturing::lang.manufacturing'), $atributos)
                ->order(21);
            });
        }
    }

    public function profitLossReportData($data)
    {
        $business_id = $data['business_id'];
        $location_id = ! empty($data['location_id']) ? $data['location_id'] : null;
        $start_date = ! empty($data['start_date']) ? $data['start_date'] : null;
        $end_date = ! empty($data['end_date']) ? $data['end_date'] : null;
        $user_id = ! empty($data['user_id']) ? $data['user_id'] : null;

        $mfgUtil = new ManufacturingUtil();

        $production_totals = $mfgUtil->getProductionTotals($business_id, $location_id, $start_date, $end_date, $user_id);

        $report_data = [
            //left side data
            [
                [
                    'value' => $production_totals['total_production_cost'],
                    'label' => __('manufacturing::lang.total_production_cost'),
                    'add_to_net_profit' => true,
                ],
            ],

            //right side data
            [],
        ];

        return $report_data;
    }
}
