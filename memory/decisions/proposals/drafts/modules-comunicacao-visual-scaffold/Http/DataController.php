<?php

/**
 * DRAFT — DataController Modules/ComunicacaoVisual.
 *
 * 3 hooks UltimatePOS (RUNBOOK-criar-modulo §4):
 *  - superadmin_package() → feature flag em /superadmin/packages
 *  - user_permissions()   → permissoes Spatie em /roles
 *  - modifyAdminMenu()    → injeta sidebar admin
 *
 * Convencao UltimatePOS: middleware AdminSidebarMenu chama
 *   Modules\ComunicacaoVisual\Http\Controllers\DataController@modifyAdminMenu
 * em cada request da sidebar.
 *
 * ⚠️ Localizacao do arquivo: Modules/ComunicacaoVisual/Http/Controllers/DataController.php
 *    (NAO em Modules/ComunicacaoVisual/Http/DataController.php — UltimatePOS espera Controllers/).
 *
 * ⚠️ NAO usar __('comvis::xxx.yyy') aqui — LegacyMenuAdapter le literal, labels saem crus.
 *    Hardcodar PT-BR (mesma regra do Modules/NFSe/Modules/ADS).
 *
 * Imitar Modules/ADS/Http/Controllers/DataController.php (validado 2026-05-03).
 */

namespace Modules\ComunicacaoVisual\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Feature flag em Superadmin > Packages.
     * Quando false pro business, modulo nao aparece na sidebar nem nas permissoes.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'comvis_module',
                'label'   => 'Modulo ComunicacaoVisual (gráfica rápida + PCP + cálculo m²)',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissoes Spatie listadas em /roles.
     * Felipe: adicionar permissoes especificas por US conforme controllers entrarem.
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'comvis.access',
                'label'   => 'ComunicacaoVisual: acessar modulo',
                'default' => false,
            ],
            [
                'value'   => 'comvis.orcamento.create',
                'label'   => 'ComunicacaoVisual: criar orcamento (US-COMVIS-001)',
                'default' => false,
            ],
            [
                'value'   => 'comvis.material.manage',
                'label'   => 'ComunicacaoVisual: gerenciar materiais e precos (US-COMVIS-002)',
                'default' => false,
            ],
            [
                'value'   => 'comvis.os.manage',
                'label'   => 'ComunicacaoVisual: gerenciar OS / Kanban PCP (US-COMVIS-003)',
                'default' => false,
            ],
            // Permissoes de US futuras (apontamento, instalacao, etc) entram em PRs especificos.
        ];
    }

    /**
     * Injeta dropdown ComunicacaoVisual na sidebar AdminLTE.
     *
     * Stub minimo: so o entry pai + 1 link pra inbox/dashboard. Felipe expande
     * conforme US-COMVIS-001/002/003 forem entregando paginas.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('ComunicacaoVisual');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'comvis_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('comvis.access');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo   = request()->segment(1) === 'comvis';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    'Comunicacao Visual',
                    function ($sub) {
                        $segment3 = request()->segment(3);

                        // PLACEHOLDER: Felipe preenche links abaixo conforme US entregar.
                        // Sprint 1 entrega so a estrutura, sem paginas funcionais ainda.
                        //
                        // Exemplo apos US-COMVIS-002 (CRUD materiais) entregar:
                        // $sub->url(url('/comvis/materiais'), 'Materiais', [
                        //     'icon'   => 'fa fas fa-layer-group',
                        //     'active' => $segment3 === 'materiais',
                        // ]);
                        //
                        // Exemplo apos US-COMVIS-001 (Calculo) entregar:
                        // $sub->url(url('/comvis/orcamento/novo'), 'Novo orcamento', [
                        //     'icon'   => 'fa fas fa-calculator',
                        //     'active' => $segment3 === 'orcamento',
                        // ]);

                        // Stub minimo Sprint 1 — link unico pra dashboard placeholder
                        // (que pode redirecionar pra /comvis/install se nao houver tela ainda).
                        $sub->url(url('/comvis'), 'Painel (em construcao)', [
                            'icon'   => 'fa fas fa-tools',
                            'active' => $segmento_ativo,
                        ]);
                    },
                    [
                        'icon'   => 'fa fas fa-print',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(50);
            }
        );
    }
}
