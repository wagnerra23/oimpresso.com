<?php

namespace Modules\PontoWr2\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo PontoWr2.
 *
 * Este controller é descoberto automaticamente pelo middleware
 * `AdminSidebarMenu` do core UltimatePOS (convenção:
 * Modules\<Nome>\Http\Controllers\DataController@modifyAdminMenu).
 *
 * Ver como referência:
 *  - Modules/Jana/Http/Controllers/DataController.php      (dropdown)
 *  - Modules/Repair/Http/Controllers/DataController.php    (item simples)
 *  - Modules/Project/Http/Controllers/DataController.php   (item simples)
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para o painel Superadmin > Packages.
     *
     * Usado pelo UltimatePOS para permitir que o superadmin inclua ou não o
     * módulo Ponto WR2 em um pacote de assinatura.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'ponto_module',
                'label'   => __('pontowr2::ponto.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem no cadastro de papéis (Roles) do
     * UltimatePOS. Mantém o mesmo prefixo usado nas rotas e no middleware
     * `ponto.access` (CheckPontoAccess).
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'ponto.access',
                'label'   => __('pontowr2::ponto.permissao_acesso'),
                'default' => false,
            ],
            [
                'value'   => 'ponto.colaboradores.manage',
                'label'   => __('pontowr2::ponto.permissao_colaboradores'),
                'default' => false,
            ],
            [
                'value'   => 'ponto.aprovacoes.manage',
                'label'   => __('pontowr2::ponto.permissao_aprovacoes'),
                'default' => false,
            ],
            [
                'value'   => 'ponto.relatorios.view',
                'label'   => __('pontowr2::ponto.permissao_relatorios'),
                'default' => false,
            ],
            [
                'value'   => 'ponto.configuracoes.manage',
                'label'   => __('pontowr2::ponto.permissao_configuracoes'),
                'default' => false,
            ],
        ];
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
            $is_ponto_enabled = $module_util->isModuleInstalled('PontoWr2');
        } else {
            $business_id = session()->get('user.business_id');
            $is_ponto_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'ponto_module',
                'superadmin_package'
            );
        }

        if (! $is_ponto_enabled) {
            return;
        }

        // Superadmin sempre vê; usuário comum precisa de ponto.access (mínimo).
        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('ponto.access');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'ponto';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    __('pontowr2::ponto.module_label'),
                    function ($sub) {
                        $sub->url(
                            route('ponto.dashboard'),
                            __('pontowr2::ponto.menu.dashboard'),
                            [
                                'icon'   => 'fa fas fa-tachometer-alt',
                                'active' => request()->segment(1) == 'ponto' && !request()->segment(2),
                            ]
                        );

                        $sub->url(
                            route('ponto.espelho.index'),
                            __('pontowr2::ponto.menu.espelho'),
                            [
                                'icon'   => 'fa fas fa-clipboard-list',
                                'active' => request()->segment(2) == 'espelho',
                            ]
                        );

                        if (auth()->user()->can('superadmin') || auth()->user()->can('ponto.aprovacoes.manage')) {
                            $sub->url(
                                route('ponto.aprovacoes.index'),
                                __('pontowr2::ponto.menu.aprovacoes'),
                                [
                                    'icon'   => 'fa fas fa-check-double',
                                    'active' => request()->segment(2) == 'aprovacoes',
                                ]
                            );
                        }

                        $sub->url(
                            route('ponto.intercorrencias.index'),
                            __('pontowr2::ponto.menu.intercorrencias'),
                            [
                                'icon'   => 'fa fas fa-exclamation-triangle',
                                'active' => request()->segment(2) == 'intercorrencias',
                            ]
                        );

                        $sub->url(
                            route('ponto.banco-horas.index'),
                            __('pontowr2::ponto.menu.banco_horas'),
                            [
                                'icon'   => 'fa fas fa-piggy-bank',
                                'active' => request()->segment(2) == 'banco-horas',
                            ]
                        );

                        $sub->url(
                            route('ponto.escalas.index'),
                            __('pontowr2::ponto.menu.escalas'),
                            [
                                'icon'   => 'fa fas fa-calendar-alt',
                                'active' => request()->segment(2) == 'escalas',
                            ]
                        );

                        $sub->url(
                            route('ponto.importacoes.index'),
                            __('pontowr2::ponto.menu.importacoes'),
                            [
                                'icon'   => 'fa fas fa-file-import',
                                'active' => request()->segment(2) == 'importacoes',
                            ]
                        );

                        $sub->url(
                            route('ponto.relatorios.index'),
                            __('pontowr2::ponto.menu.relatorios'),
                            [
                                'icon'   => 'fa fas fa-chart-bar',
                                'active' => request()->segment(2) == 'relatorios',
                            ]
                        );

                        if (auth()->user()->can('superadmin') || auth()->user()->can('ponto.colaboradores.manage')) {
                            $sub->url(
                                route('ponto.colaboradores.index'),
                                __('pontowr2::ponto.menu.colaboradores'),
                                [
                                    'icon'   => 'fa fas fa-users',
                                    'active' => request()->segment(2) == 'colaboradores',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('ponto.configuracoes.manage')) {
                            $sub->url(
                                route('ponto.configuracoes.index'),
                                __('pontowr2::ponto.menu.configuracoes'),
                                [
                                    'icon'   => 'fa fas fa-cog',
                                    'active' => request()->segment(2) == 'configuracoes',
                                ]
                            );
                        }
                    },
                    [
                        'icon'  => 'fa fas fa-business-time',
                        'style' => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(88); // logo abaixo do HRM/Essentials (order=87)
            }
        );
    }
}
