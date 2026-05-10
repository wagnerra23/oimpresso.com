<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Modules/ComunicacaoVisual (vertical CNAE 1813-0/01).
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\ComunicacaoVisual\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 * Os hooks `superadmin_package` e `user_permissions` são chamados na tela de Roles/Packages.
 *
 * Sprint 1: scaffold com 6 permissões CV + sidebar com 4 sub-itens.
 * NOTA: hardcoded PT-BR em labels — NÃO usar __('alias::key') em DataController
 * (LegacyMenuAdapter não resolve traduções — RUNBOOK §troubleshooting).
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
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
                'name'    => 'comunicacao_visual_module',
                'label'   => 'Módulo Comunicação Visual (CNAE 1813 — gráfica/com.visual)',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo na tela de Roles.
     * 6 permissões cobrindo os 4 sub-módulos Sprint 1.
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'comvis.orcamento.view',
                'label'   => 'Com. Visual: ver orçamentos',
                'default' => false,
            ],
            [
                'value'   => 'comvis.orcamento.create',
                'label'   => 'Com. Visual: criar orçamentos',
                'default' => false,
            ],
            [
                'value'   => 'comvis.material.manage',
                'label'   => 'Com. Visual: gerenciar materiais',
                'default' => false,
            ],
            [
                'value'   => 'comvis.os.view',
                'label'   => 'Com. Visual: ver ordens de serviço',
                'default' => false,
            ],
            [
                'value'   => 'comvis.os.update_status',
                'label'   => 'Com. Visual: atualizar status de OS',
                'default' => false,
            ],
            [
                'value'   => 'comvis.apontamento.create',
                'label'   => 'Com. Visual: registrar apontamentos',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta item "Comunicação Visual" na sidebar do AdminLTE.
     * Sub-itens: Orçamentos, Ordens de Serviço, Materiais, Apontamentos.
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
                'comunicacao_visual_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('comvis.orcamento.view')
            || auth()->user()->can('comvis.os.view');

        if (! $usuario_pode_ver) {
            return;
        }

        $segmento_ativo = request()->segment(1) === 'comunicacao-visual';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($segmento_ativo) {
                $menu->dropdown(
                    'Comunicação Visual',
                    function ($sub) {
                        $segment3 = request()->segment(3);

                        $sub->url(url('/comunicacao-visual/admin/orcamentos'), 'Orçamentos', [
                            'icon'   => 'fa fas fa-file-invoice',
                            'active' => $segment3 === 'orcamentos',
                        ]);

                        $sub->url(url('/comunicacao-visual/admin/os'), 'Ordens de Serviço', [
                            'icon'   => 'fa fas fa-tasks',
                            'active' => $segment3 === 'os',
                        ]);

                        $sub->url(url('/comunicacao-visual/admin/materiais'), 'Materiais', [
                            'icon'   => 'fa fas fa-layer-group',
                            'active' => $segment3 === 'materiais',
                        ]);

                        $sub->url(url('/comunicacao-visual/admin/apontamentos'), 'Apontamentos', [
                            'icon'   => 'fa fas fa-clock',
                            'active' => $segment3 === 'apontamentos',
                        ]);
                    },
                    [
                        'icon'   => 'fa fas fa-print',
                        'active' => $segmento_ativo,
                    ]
                )->order(55);
            }
        );
    }
}
