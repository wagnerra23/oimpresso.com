<?php

namespace Modules\NfeBrasil\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo NfeBrasil.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\NfeBrasil\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * Observações importantes do contexto deste módulo:
 *
 *  - `Modules/NfeBrasil/Resources/lang/` está vazio (apenas .gitkeep).
 *    Usamos strings literais em PT-BR. Quando os lang files forem
 *    criados, trocar pelas chaves `nfebrasil::lang.*` (ou
 *    `nfebrasil::nfebrasil.*` conforme a convenção escolhida).
 *
 *  - Rotas web (Modules/NfeBrasil/Routes/web.php) hoje têm apenas:
 *      - prefix `nfebrasil/install` (Install/uninstall/update)
 *      - resource `nfebrasil` (named `nfebrasil.*`) — placeholder das
 *        próximas sub-ondas, ainda CRUD vazio.
 *    Os itens "Emitir NF-e", "Consultar", "SPED" abaixo são placeholders
 *    apontando para rotas que ainda não existem; vão habilitar conforme
 *    o roadmap (NFC-e em 1 clique, NF-e B2B, SPED).
 *
 * @see Modules/PontoWr2/Http/Controllers/DataController.php   (template)
 * @see Modules/NfeBrasil/module.json                          (descrição)
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para Superadmin > Packages.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'nfebrasil_module',
                'label'   => 'Módulo NF-e Brasil',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem na tela de Roles do UltimatePOS.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'nfebrasil.access',
                'label'   => 'NF-e Brasil: acesso ao módulo',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.emit.manage',
                'label'   => 'NF-e Brasil: emitir notas fiscais',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.consult.view',
                'label'   => 'NF-e Brasil: consultar notas emitidas',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.sped.view',
                'label'   => 'NF-e Brasil: gerar/exportar SPED',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.settings.manage',
                'label'   => 'NF-e Brasil: gerenciar configurações fiscais',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('NfeBrasil');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'nfebrasil_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('nfebrasil.access')
            || auth()->user()->can('nfebrasil.emit.manage')
            || auth()->user()->can('nfebrasil.consult.view');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'nfebrasil';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    'NF-e Brasil',
                    function ($sub) {
                        $sub->url(
                            url('/nfebrasil'),
                            'Painel',
                            [
                                'icon'   => 'fa fas fa-tachometer-alt',
                                'active' => request()->segment(1) == 'nfebrasil' && ! request()->segment(2),
                            ]
                        );

                        if (auth()->user()->can('superadmin') || auth()->user()->can('nfebrasil.emit.manage')) {
                            $sub->url(
                                url('/nfebrasil/create'),
                                'Emitir NF-e / NFC-e',
                                [
                                    'icon'   => 'fa fas fa-file-invoice-dollar',
                                    'active' => request()->segment(2) == 'create',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('nfebrasil.consult.view')) {
                            $sub->url(
                                url('/nfebrasil?status=emitidas'),
                                'Consultar Notas',
                                [
                                    'icon'   => 'fa fas fa-search',
                                    'active' => false,
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('nfebrasil.sped.view')) {
                            $sub->url(
                                url('/nfebrasil/sped'),
                                'SPED Fiscal',
                                [
                                    'icon'   => 'fa fas fa-file-archive',
                                    'active' => request()->segment(2) == 'sped',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('nfebrasil.settings.manage')) {
                            $sub->url(
                                url('/nfebrasil/settings'),
                                'Configurações Fiscais',
                                [
                                    'icon'   => 'fa fas fa-cog',
                                    'active' => request()->segment(2) == 'settings',
                                ]
                            );
                        }
                    },
                    [
                        'icon'   => 'fa fas fa-file-invoice-dollar',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(95);
            }
        );
    }
}
