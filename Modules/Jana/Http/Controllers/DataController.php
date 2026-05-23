<?php

namespace Modules\Jana\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Copiloto.
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\Jana\Http\Controllers\DataController@modifyAdminMenu).
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
                'value'   => 'jana.access',
                'label'   => __('copiloto::copiloto.permissao_acesso'),
                'default' => false,
            ],
            [
                'value'   => 'jana.chat',
                'label'   => __('copiloto::copiloto.permissao_chat'),
                'default' => false,
            ],
            [
                'value'   => 'jana.metas.manage',
                'label'   => __('copiloto::copiloto.permissao_metas'),
                'default' => false,
            ],
            [
                'value'   => 'jana.superadmin',
                'label'   => __('copiloto::copiloto.permissao_superadmin'),
                'default' => false,
            ],
            [
                'value'   => 'jana.admin.custos.view',
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
            $is_enabled = $module_util->isModuleInstalled('Jana');
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
            || auth()->user()->can('jana.access')
            || auth()->user()->can('jana.chat');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'jana';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // ADR 0180 Fase 4 Wave C TOPO (2026-05-21): entry dropdown principal
                // declara atributos extras propagados pelo LegacyMenuAdapter pro
                // frontend Sidebar.tsx (v3 — grupo 'ia' no TOPO):
                //  - `shortcut` G I → atalho kbd canônico (overlay Fase 8)
                //  - `primary`     → "Conversar com Jana" (entry-point IA do módulo)
                //  - `ghosts`      → 6 sub-views (conversar/dashboard/metas/alertas/custos/plataforma)
                //
                // group: 'ia' canon — LegacyMenuAdapter propaga pro Sidebar.tsx,
                // que renderiza Jana no TOPO junto com KB/Brief/SRS (ghosts de IA).
                //
                // hrefs absolutos (não usa route() helper aqui pra evitar interpretação
                // no LegacyMenuAdapter::toRelative). Permission gates específicos
                // (jana.chat, jana.metas.manage, jana.admin.custos.view, jana.superadmin)
                // permanecem enforce nos Controllers individuais — gate global
                // hasThePermissionInSubscription já cobre módulo on/off na entry.
                $menu->dropdown(
                    __('copiloto::copiloto.module_label'),
                    function ($sub) {
                        // Conversar — entry-point do módulo (chat IA)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.chat')) {
                            $sub->url(
                                route('jana.chat.index'),
                                __('copiloto::copiloto.menu.conversar'),
                                [
                                    'icon'   => 'fa fas fa-comments',
                                    'active' => request()->segment(1) == 'jana'
                                                && ! request()->segment(2),
                                ]
                            );
                        }

                        // Dashboard
                        $sub->url(
                            route('jana.dashboard.index'),
                            __('copiloto::copiloto.menu.dashboard'),
                            [
                                'icon'   => 'fa fas fa-tachometer-alt',
                                'active' => request()->segment(2) == 'dashboard',
                            ]
                        );

                        // Metas
                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.metas.manage')) {
                            $sub->url(
                                route('jana.metas.index'),
                                __('copiloto::copiloto.menu.metas'),
                                [
                                    'icon'   => 'fa fas fa-bullseye',
                                    'active' => request()->segment(2) == 'metas',
                                ]
                            );
                        }

                        // Alertas
                        $sub->url(
                            route('jana.alertas.index'),
                            __('copiloto::copiloto.menu.alertas'),
                            [
                                'icon'   => 'fa fas fa-bell',
                                'active' => request()->segment(2) == 'alertas',
                            ]
                        );

                        // Custos de IA (admin do business — US-COPI-070)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.admin.custos.view')) {
                            $sub->url(
                                route('jana.admin.custos.index'),
                                __('copiloto::copiloto.menu.custos'),
                                [
                                    'icon'   => 'fa fas fa-coins',
                                    'active' => request()->segment(2) == 'admin'
                                                && request()->segment(3) == 'custos',
                                ]
                            );
                        }

                        // Plataforma (superadmin-only)
                        if (auth()->user()->can('superadmin') || auth()->user()->can('jana.superadmin')) {
                            $sub->url(
                                route('jana.superadmin.metas'),
                                __('copiloto::copiloto.menu.plataforma'),
                                [
                                    'icon'   => 'fa fas fa-building',
                                    'active' => request()->segment(2) == 'superadmin',
                                ]
                            );
                        }
                    },
                    [
                        'icon'     => 'fa fas fa-compass',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo,
                        'group'    => 'ia',
                        'shortcut' => 'G I',
                        'primary'  => [
                            'label'    => 'Conversar com Jana',
                            'href'     => '/ia',
                            'shortcut' => 'N',
                        ],
                        // ADR 0182 + GUIA-SIDEBAR-V3 Wagner 2026-05-21: hub IA com
                        // sub-views canon do guia (Copiloto/Brief/Memórias/KB/Regras)
                        // + ghosts internos Jana (Dashboard/Metas/Custos).
                        // Labels CURTOS (≤2 palavras). PageHeaderTabs auto-promove ghost
                        // ativo inline mesmo se index >= maxVisible.
                        //
                        // Wagner 2026-05-22: hrefs /jana → /ia (vertical-slice IA piloto
                        // sidebar v3 — URL canon casa com label "IA" do topo).
                        'ghosts'   => [
                            // 5 destinos canon do guia
                            ['key' => 'copiloto',  'label' => 'Copiloto',  'href' => '/ia'],
                            ['key' => 'brief',     'label' => 'Brief',     'href' => '/ia/brief'],
                            ['key' => 'memorias',  'label' => 'Memórias',  'href' => '/ia/memorias'],
                            ['key' => 'kb',        'label' => 'KB',        'href' => '/ia/kb'],
                            ['key' => 'regras',    'label' => 'Regras',    'href' => '/ia/regras'],
                            // Operacional interno (descoberta admin)
                            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/ia/dashboard'],
                            ['key' => 'metas',     'label' => 'Metas',     'href' => '/ia/metas'],
                            ['key' => 'custos',    'label' => 'Custos',    'href' => '/ia/admin/custos'],
                            // Wagner 2026-05-22: ADS vai pra dentro da Jana (entry sidebar removida).
                            ['key' => 'ads',       'label' => 'ADS',       'href' => '/ads'],
                            // Wagner 2026-05-22 P2: zera 2 órfãs (telas Jana Admin Governança + Qualidade).
                            ['key' => 'governanca-jana', 'label' => 'Governança Jana', 'href' => '/ia/admin/governanca'],
                            ['key' => 'qualidade-jana',  'label' => 'Qualidade IA',    'href' => '/ia/admin/qualidade'],
                        ],
                    ]
                )->order(90); // Logo após PontoWr2 (88)
            }
        );
    }
}
