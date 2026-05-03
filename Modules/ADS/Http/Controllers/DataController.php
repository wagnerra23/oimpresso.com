<?php

namespace Modules\ADS\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo ADS (Adaptive Decision System).
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\ADS\Http\Controllers\DataController@modifyAdminMenu` em cada request
 * da sidebar admin (e os hooks superadmin_package/user_permissions na tela de Roles).
 *
 * Espelha o topnav declarativo em Modules/ADS/Resources/menus/topnav.php.
 *
 * @see memory/requisitos/ADS/SPEC.md
 * @see memory/requisitos/ADS/adr/arq/ARQ-0001-ads-escopo-e-papel-unico.md
 */
class DataController extends Controller
{
    /**
     * Feature flag em Superadmin > Packages.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'ads_module',
                'label'   => __('ads::ads.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo na tela de Roles.
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'ads.access',
                'label'   => __('ads::ads.permissao_acesso'),
                'default' => false,
            ],
            [
                'value'   => 'ads.decisoes.review',
                'label'   => __('ads::ads.permissao_decisoes_review'),
                'default' => false,
            ],
            [
                'value'   => 'ads.decisoes.approve',
                'label'   => __('ads::ads.permissao_decisoes_approve'),
                'default' => false,
            ],
            [
                'value'   => 'ads.policy.manage',
                'label'   => __('ads::ads.permissao_policy_manage'),
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item ADS na sidebar do AdminLTE.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('ADS');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'ads_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('ads.access')
            || auth()->user()->can('ads.decisoes.review');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo   = request()->segment(1) === 'ads';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    __('ads::ads.module_label'),
                    function ($sub) {
                        $sub->url(
                            url('/ads/admin/decisoes'),
                            __('ads::ads.menu.decisoes'),
                            [
                                'icon'   => 'fa fas fa-brain',
                                'active' => request()->segment(2) === 'admin' && request()->segment(3) === 'decisoes',
                            ]
                        );
                    },
                    [
                        'icon'   => 'fa fas fa-microchip',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(98);
            }
        );
    }
}
