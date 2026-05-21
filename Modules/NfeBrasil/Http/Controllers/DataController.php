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
            [
                'value'   => 'nfe.configuracao.manage',
                'label'   => 'NF-e Brasil: configurar certificado A1',
                'default' => false,
            ],
            [
                'value'   => 'nfe.tributacao.manage',
                'label'   => 'NF-e Brasil: gerenciar tributação (regras NCM + default)',
                'default' => false,
            ],
            [
                'value'   => 'nfe.manifestacao.view',
                'label'   => 'NF-e Brasil: ver NF-e recebidas (manifestação)',
                'default' => false,
            ],
            [
                'value'   => 'nfe.manifestacao.manage',
                'label'   => 'NF-e Brasil: manifestar NF-e recebidas (Confirmação/Ciência/Desconhecimento/Não Realizada)',
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
            || auth()->user()->can('nfebrasil.consult.view')
            || auth()->user()->can('nfe.configuracao.manage')
            || auth()->user()->can('nfe.tributacao.manage')
            || auth()->user()->can('nfe.manifestacao.view')
            || auth()->user()->can('nfe.manifestacao.manage');

        if (! $usuario_pode_ver) {
            return;
        }

        // Agrupamento visual em "FISCAL" acontece no frontend (SIDEBAR_GROUPS
        // em resources/js/Components/cockpit/Sidebar.tsx). DataController
        // publica o dropdown standalone do módulo.
        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'nfebrasil';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // ADR 0180 Fase 4 Wave D FINANÇAS+PESSOAS (2026-05-21): entry
                // principal NF-e Brasil (módulo principal do grupo FISCAL) declara
                // atalho kbd + primary action + ghosts tabs no `attributes` do
                // dropdown — LegacyMenuAdapter propaga pro frontend Sidebar.tsx.
                //  - `shortcut` G X → atalho overlay (X = fisXal, evita conflito
                //    com G F/G S/G C/G O/G Y/G P já usados em outras ondas)
                //  - `primary`     → "Emitir NF-e / NFC-e" (ação canon do módulo)
                //  - `ghosts`      → 8 sub-views da tela /nfebrasil/* + manifestação
                //
                // hrefs absolutos (LegacyMenuAdapter::toRelative espera path string).
                // Permission gates específicos (nfebrasil.emit.manage, .consult.view,
                // .sped.view, .settings.manage, .configuracao.manage, .tributacao.manage,
                // .manifestacao.*) continuam enforce nos Controllers — gate global
                // hasThePermissionInSubscription já cobre módulo on/off na entry.
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

                        if (auth()->user()->can('superadmin') || auth()->user()->can('nfe.configuracao.manage')) {
                            $sub->url(
                                url('/nfe-brasil/configuracao/certificado'),
                                'Certificado A1',
                                [
                                    'icon'   => 'fa fas fa-key',
                                    'active' => request()->is('nfe-brasil/configuracao/certificado*'),
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('nfe.tributacao.manage')) {
                            $sub->url(
                                url('/nfe-brasil/tributacao'),
                                'Tributação',
                                [
                                    'icon'   => 'fa fas fa-percent',
                                    'active' => request()->is('nfe-brasil/tributacao*'),
                                ]
                            );
                        }

                        // US-NFE-052 (ADR 0116) — manifestação destinatário
                        if (
                            auth()->user()->can('superadmin')
                            || auth()->user()->can('nfe.manifestacao.view')
                            || auth()->user()->can('nfe.manifestacao.manage')
                        ) {
                            $sub->url(
                                url('/nfe-brasil/manifestacao'),
                                'Notas recebidas',
                                [
                                    'icon'   => 'fa fas fa-inbox',
                                    'active' => request()->is('nfe-brasil/manifestacao*'),
                                ]
                            );
                        }
                    },
                    [
                        'icon'     => 'fa fas fa-file-invoice-dollar',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo,
                        'shortcut' => 'G X',
                        'primary'  => [
                            'label'    => 'Emitir NF-e',
                            'href'     => '/nfebrasil/create',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'painel',        'label' => 'Painel',                'href' => '/nfebrasil'],
                            ['key' => 'emitir',        'label' => 'Emitir NF-e / NFC-e',   'href' => '/nfebrasil/create'],
                            ['key' => 'consultar',     'label' => 'Consultar Notas',       'href' => '/nfebrasil?status=emitidas'],
                            ['key' => 'sped',          'label' => 'SPED Fiscal',           'href' => '/nfebrasil/sped'],
                            ['key' => 'settings',      'label' => 'Configurações Fiscais', 'href' => '/nfebrasil/settings'],
                            ['key' => 'certificado',   'label' => 'Certificado A1',        'href' => '/nfe-brasil/configuracao/certificado'],
                            ['key' => 'tributacao',    'label' => 'Tributação',            'href' => '/nfe-brasil/tributacao'],
                            ['key' => 'manifestacao',  'label' => 'Notas recebidas',       'href' => '/nfe-brasil/manifestacao'],
                        ],
                    ]
                )->order(95);
            }
        );
    }
}
