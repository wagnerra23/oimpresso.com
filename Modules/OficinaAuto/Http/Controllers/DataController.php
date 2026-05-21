<?php

namespace Modules\OficinaAuto\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Modules/OficinaAuto (vertical CNAEs 4520/2212/4581).
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\OficinaAuto\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * Hooks `superadmin_package` e `user_permissions` são chamados na tela de Roles/Packages.
 *
 * V0 scaffold: 8 permissões + sidebar com 2 sub-itens (Veículos, Ordens de Serviço).
 * NOTA: hardcoded PT-BR em labels — NÃO usar __('alias::key') em DataController
 * (LegacyMenuAdapter não resolve traduções — RUNBOOK §troubleshooting).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
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
                'name'    => 'oficina_auto_module',
                'label'   => 'Módulo Oficina Auto (CNAEs 4520/2212/4581 — oficinas automotivas)',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo na tela de Roles.
     * 8 permissões cobrindo CRUD Vehicle + CRUD ServiceOrder (ADR 0137).
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'oficinaauto.access',
                'label'   => 'Oficina Auto: acessar módulo',
                'default' => false,
            ],
            [
                'value'   => 'oficinaauto.vehicle.view',
                'label'   => 'Oficina Auto: ver veículos',
                'default' => false,
            ],
            [
                'value'   => 'oficinaauto.vehicle.create',
                'label'   => 'Oficina Auto: criar veículos',
                'default' => false,
            ],
            [
                'value'   => 'oficinaauto.vehicle.update',
                'label'   => 'Oficina Auto: editar veículos',
                'default' => false,
            ],
            [
                'value'   => 'oficinaauto.vehicle.delete',
                'label'   => 'Oficina Auto: excluir veículos',
                'default' => false,
            ],
            [
                'value'   => 'oficinaauto.service_order.view',
                'label'   => 'Oficina Auto: ver ordens de serviço',
                'default' => false,
            ],
            [
                'value'   => 'oficinaauto.service_order.create',
                'label'   => 'Oficina Auto: criar ordens de serviço',
                'default' => false,
            ],
            [
                'value'   => 'oficinaauto.service_order.update',
                'label'   => 'Oficina Auto: editar ordens de serviço',
                'default' => false,
            ],
            [
                'value'   => 'oficinaauto.service_order.delete',
                'label'   => 'Oficina Auto: excluir ordens de serviço',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta item "Oficina Auto" na sidebar do AdminLTE.
     * Sub-itens: Veículos, Ordens de Serviço.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('OficinaAuto');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'oficina_auto_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('oficinaauto.access')
            || auth()->user()->can('oficinaauto.vehicle.view')
            || auth()->user()->can('oficinaauto.service_order.view');

        if (! $usuario_pode_ver) {
            return;
        }

        $segmento_ativo = request()->segment(1) === 'oficina-auto';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($segmento_ativo) {
                // ADR 0180 Fase 4 Wave B (2026-05-21): dropdown principal declara
                // atalho kbd + primary action + ghosts tabs no `attributes`.
                //  - `shortcut` G Y → atalho overlay (não-conflito com Repair G O)
                //  - `primary`     → "Nova OS" via ServiceOrderController@create
                //  - `ghosts`      → Veículos, Ordens de Serviço, Produção (Kanban
                //    caçambas Martinho 2026-05-13)
                $menu->dropdown(
                    'Oficina Auto',
                    function ($sub) {
                        $segment2 = request()->segment(2);

                        $sub->url(url('/oficina-auto/veiculos'), 'Veículos', [
                            'icon'   => 'fa fas fa-car',
                            'active' => $segment2 === 'veiculos',
                        ]);

                        $sub->url(url('/oficina-auto/ordens-servico'), 'Ordens de Serviço', [
                            'icon'   => 'fa fas fa-tools',
                            'active' => $segment2 === 'ordens-servico',
                        ]);
                    },
                    [
                        'icon'     => 'fa fas fa-wrench',
                        'active'   => $segmento_ativo,
                        'shortcut' => 'G Y',
                        'primary'  => [
                            'label'    => 'Nova OS',
                            'href'     => '/oficina-auto/ordens-servico/create',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'veiculos',          'label' => 'Veículos',           'href' => '/oficina-auto/veiculos'],
                            ['key' => 'ordens-servico',    'label' => 'Ordens de Serviço',  'href' => '/oficina-auto/ordens-servico'],
                            ['key' => 'producao-oficina',  'label' => 'Produção',           'href' => '/oficina-auto/producao-oficina'],
                        ],
                    ]
                )->order(56);
            }
        );
    }
}
