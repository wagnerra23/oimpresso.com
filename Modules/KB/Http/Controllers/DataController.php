<?php

namespace Modules\KB\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo KB (Knowledge Base).
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\KB\Http\Controllers\DataController@modifyAdminMenu).
 *
 * Espelha o topnav declarativo em Modules/KB/Resources/menus/topnav.php.
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para o painel Superadmin > Packages.
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'kb_module',
                'label'   => __('kb::kb.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões expostas no cadastro de papéis (Roles) do UltimatePOS.
     *
     * NOTA: a permissão Spatie real usada pelo KbController continua sendo
     * `copiloto.mcp.memory.manage` (mantida pra evitar migration de rename).
     * As chaves abaixo são placeholders pra agregação visual no
     * PermissionRegistry novo (pacote contracts em flight). Rename em PR
     * separado com migration própria.
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'kb.view',
                'label'   => __('kb::kb.permissao_view'),
                'default' => false,
            ],
            [
                'value'   => 'kb.softdelete',
                'label'   => __('kb::kb.permissao_softdelete'),
                'default' => false,
            ],
            [
                'value'   => 'kb.restore',
                'label'   => __('kb::kb.permissao_restore'),
                'default' => false,
            ],
            [
                'value'   => 'kb.history.view',
                'label'   => __('kb::kb.permissao_history'),
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('KB');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'kb_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        // Visibilidade: superadmin OU permission Spatie atual `copiloto.mcp.memory.manage`.
        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('jana.mcp.memory.manage');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'kb';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // ADR 0180 Fase 4 Wave C TOPO (2026-05-21): entry KB declara
                // `group: 'ia'` pro frontend Sidebar.tsx (v3) renderizar KB
                // no TOPO junto com Jana (ghost de IA). KB NÃO declara shortcut
                // próprio (instrução Wave C — ghost de Jana, atalho G I cobre).
                // KB é módulo single-page admin (apenas /kb), então ghosts é
                // omitido (LegacyMenuAdapter já trata array vazio como ausente).
                $menu->url(
                    route('kb.index'),
                    __('kb::kb.module_label'),
                    [
                        'icon'   => 'fa fas fa-book-open',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                        'group'  => 'ia',
                    ]
                )->order(91); // Logo após Copiloto (90)
            }
        );
    }
}
