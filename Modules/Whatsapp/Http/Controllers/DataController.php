<?php

namespace Modules\Whatsapp\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Whatsapp.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\Whatsapp\Http\Controllers\DataController@modifyAdminMenu` em cada request
 * da sidebar admin (e os hooks superadmin_package/user_permissions na tela de Roles).
 *
 * Espelha o topnav declarativo em Modules/Whatsapp/Resources/menus/topnav.php.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md
 * @see memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'whatsapp_module',
                'label'   => 'Módulo Whatsapp (Z-API + Meta Cloud)',
                'default' => false,
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            ['value' => 'whatsapp.access',           'label' => 'Whatsapp: acessar módulo',                  'default' => false],
            ['value' => 'whatsapp.send',             'label' => 'Whatsapp: enviar mensagem manual',          'default' => false],
            ['value' => 'whatsapp.assign',           'label' => 'Whatsapp: atribuir conversa a atendente',   'default' => false],
            ['value' => 'whatsapp.templates.manage', 'label' => 'Whatsapp: gerenciar templates HSM/locais',  'default' => false],
            ['value' => 'whatsapp.settings.manage',  'label' => 'Whatsapp: configurar drivers (Z-API/Meta)', 'default' => false],
            ['value' => 'whatsapp.metricas.view',    'label' => 'Whatsapp: ver métricas (custo/deflection)', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Whatsapp');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'whatsapp_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('whatsapp.access');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo   = request()->segment(1) === 'whatsapp';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    'Whatsapp',
                    function ($sub) {
                        $segment2 = request()->segment(2);

                        $sub->url(url('/whatsapp/conversations'), 'Conversas', [
                            'icon'   => 'fa fas fa-comments',
                            'active' => $segment2 === 'conversations',
                        ]);
                        $sub->url(url('/whatsapp/templates'), 'Templates', [
                            'icon'   => 'fa fas fa-file-alt',
                            'active' => $segment2 === 'templates',
                        ]);
                        $sub->url(url('/whatsapp/settings'), 'Configurações', [
                            'icon'   => 'fa fas fa-cog',
                            'active' => $segment2 === 'settings',
                        ]);
                    },
                    [
                        'icon'   => 'fa fab fa-whatsapp',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(95);
            }
        );
    }
}
