<?php

namespace Modules\NFSe\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo NFSe.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\NFSe\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * @see memory/09-modulos-ultimatepos.md — padrão DataController + sidebar
 * @see Modules/NfeBrasil/Http/Controllers/DataController.php — template
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para Superadmin > Packages.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'nfse_module',
                'label'   => 'Módulo NFSe',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem na tela de Roles do UltimatePOS.
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'nfse.view',
                'label'   => 'NFSe: visualizar notas',
                'default' => false,
            ],
            [
                'value'   => 'nfse.emit',
                'label'   => 'NFSe: emitir nota fiscal de serviço',
                'default' => false,
            ],
            [
                'value'   => 'nfse.cancel',
                'label'   => 'NFSe: cancelar nota fiscal',
                'default' => false,
            ],
            [
                'value'   => 'nfse.settings',
                'label'   => 'NFSe: gerenciar configurações fiscais',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('NFSe');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'nfse_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('nfse.view')
            || auth()->user()->can('nfse.emit');

        if (! $usuario_pode_ver) {
            return;
        }

        // Agrupamento visual em "FISCAL" acontece no frontend (SIDEBAR_GROUPS
        // em resources/js/Components/cockpit/Sidebar.tsx). DataController
        // publica o dropdown standalone do módulo.
        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo   = request()->segment(1) == 'nfse';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // ADR 0180 Fase 4 Wave D FINANÇAS+PESSOAS (2026-05-21): entry NFSe
                // é ghost do módulo principal Fiscal/NfeBrasil — sem shortcut próprio
                // (apenas NfeBrasil G X). Primary action "Emitir NFSe" + ghosts
                // espelham sub-views próprias declarados pro frontend Sidebar.tsx.
                $menu->dropdown(
                    'NFSe',
                    function ($sub) {
                        // Listagem (US-NFSE-008)
                        $sub->url(
                            url('/nfse'),
                            'Notas Emitidas',
                            [
                                'icon'   => 'fa fas fa-list',
                                'active' => request()->segment(1) == 'nfse' && ! request()->segment(2),
                            ]
                        );

                        // Emissão (US-NFSE-009)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('nfse.emit')) {
                            $sub->url(
                                url('/nfse/emitir'),
                                'Emitir NFSe',
                                [
                                    'icon'   => 'fa fas fa-plus-circle',
                                    'active' => request()->segment(2) == 'emitir',
                                ]
                            );
                        }

                        // Configurações (US-NFSE-014)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('nfse.settings')) {
                            $sub->url(
                                url('/nfse/config'),
                                'Configurações',
                                [
                                    'icon'   => 'fa fas fa-cog',
                                    'active' => request()->segment(2) == 'config',
                                ]
                            );
                        }
                    },
                    [
                        'icon'    => 'fa fas fa-file-invoice',
                        'style'   => 'background-color:' . $background_color,
                        'active'  => $segmento_ativo,
                        'primary' => [
                            'label'    => 'Emitir NFSe',
                            'href'     => '/nfse/emitir',
                            'shortcut' => 'N',
                        ],
                        'ghosts'  => [
                            ['key' => 'notas-emitidas', 'label' => 'Notas Emitidas', 'href' => '/nfse'],
                            ['key' => 'emitir',         'label' => 'Emitir NFSe',    'href' => '/nfse/emitir'],
                            ['key' => 'config',         'label' => 'Configurações',  'href' => '/nfse/config'],
                        ],
                    ]
                )->order(96);
            }
        );
    }
}
