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
     * Injeta item "Comunicação Visual" na sidebar.
     *
     * 2026-05-26 (Wagner reportou módulo quebrado): substituído dropdown
     * 4-sub-items (Orçamentos/OS/Materiais/Apontamentos) que apontava pra
     * URLs /comunicacao-visual/admin/* INEXISTENTES — Sprint 2 nunca
     * entregou as 4 telas Inertia. Cliques no sidebar davam 404.
     *
     * Estado novo: entry single top-level apontando pra /comunicacao-visual
     * (rota nova em Routes/web.php → Inertia::render('ComunicacaoVisual/Index')
     * stub que lista as 4 áreas como "em construção"). Quando Sprint 2 entregar
     * as telas, ghosts podem voltar via attribute 'ghosts' canon ADR 0180.
     *
     * APIs /comunicacao-visual/api/* continuam ativas (Sprint 1 entrega).
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
                $menu->url(
                    url('/comunicacao-visual'),
                    'Comunicação Visual',
                    [
                        'icon'   => 'fa fas fa-print',
                        'active' => $segmento_ativo,
                        // group: legacy default → LEGACY_GROUP_MAP frontend
                        // mapeia pra 'producao' (vertical gráfica = produção).
                        'group'  => 'producao',
                    ]
                )->order(55);
            }
        );
    }
}
