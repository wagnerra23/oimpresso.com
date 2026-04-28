<?php

namespace Modules\Copiloto\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Copiloto.
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\Copiloto\Http\Controllers\DataController@modifyAdminMenu).
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
                'value'   => 'copiloto.access',
                'label'   => __('copiloto::copiloto.permissao_acesso'),
                'default' => false,
            ],
            [
                'value'   => 'copiloto.chat',
                'label'   => __('copiloto::copiloto.permissao_chat'),
                'default' => false,
            ],
            [
                'value'   => 'copiloto.metas.manage',
                'label'   => __('copiloto::copiloto.permissao_metas'),
                'default' => false,
            ],
            [
                'value'   => 'copiloto.superadmin',
                'label'   => __('copiloto::copiloto.permissao_superadmin'),
                'default' => false,
            ],
            [
                'value'   => 'copiloto.admin.custos.view',
                'label'   => __('copiloto::copiloto.permissao_admin_custos'),
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
            $is_enabled = $module_util->isModuleInstalled('Copiloto');
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
            || auth()->user()->can('copiloto.access')
            || auth()->user()->can('copiloto.chat');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'copiloto';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    __('copiloto::copiloto.module_label'),
                    function ($sub) {
                        // Conversar — entry-point do módulo (chat IA)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('copiloto.chat')) {
                            $sub->url(
                                route('copiloto.chat.index'),
                                __('copiloto::copiloto.menu.conversar'),
                                [
                                    'icon'   => 'fa fas fa-comments',
                                    'active' => request()->segment(1) == 'copiloto'
                                                && ! request()->segment(2),
                                ]
                            );
                        }

                        // Dashboard
                        $sub->url(
                            route('copiloto.dashboard.index'),
                            __('copiloto::copiloto.menu.dashboard'),
                            [
                                'icon'   => 'fa fas fa-tachometer-alt',
                                'active' => request()->segment(2) == 'dashboard',
                            ]
                        );

                        // Metas
                        if (auth()->user()->can('superadmin') || auth()->user()->can('copiloto.metas.manage')) {
                            $sub->url(
                                route('copiloto.metas.index'),
                                __('copiloto::copiloto.menu.metas'),
                                [
                                    'icon'   => 'fa fas fa-bullseye',
                                    'active' => request()->segment(2) == 'metas',
                                ]
                            );
                        }

                        // Alertas
                        $sub->url(
                            route('copiloto.alertas.index'),
                            __('copiloto::copiloto.menu.alertas'),
                            [
                                'icon'   => 'fa fas fa-bell',
                                'active' => request()->segment(2) == 'alertas',
                            ]
                        );

                        // Custos de IA (admin do business — US-COPI-070)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('copiloto.admin.custos.view')) {
                            $sub->url(
                                route('copiloto.admin.custos.index'),
                                __('copiloto::copiloto.menu.custos'),
                                [
                                    'icon'   => 'fa fas fa-coins',
                                    'active' => request()->segment(2) == 'admin'
                                                && request()->segment(3) == 'custos',
                                ]
                            );
                        }

                        // Plataforma (superadmin-only)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('copiloto.superadmin')) {
                            $sub->url(
                                route('copiloto.superadmin.metas'),
                                __('copiloto::copiloto.menu.plataforma'),
                                [
                                    'icon'   => 'fa fas fa-building',
                                    'active' => request()->segment(2) == 'superadmin',
                                ]
                            );
                        }
                    },
                    [
                        'icon'   => 'fa fas fa-compass',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(90); // Logo após PontoWr2 (88)
            }
        );
    }
}
