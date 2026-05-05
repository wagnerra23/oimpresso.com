<?php

namespace Modules\ProjectMgmt\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo ProjectMgmt.
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\ProjectMgmt\Http\Controllers\DataController@modifyAdminMenu).
 *
 * Espelha o topnav declarativo em Modules/ProjectMgmt/Resources/menus/topnav.php.
 *
 * IMPORTANTE: as permissions reusam `copiloto.mcp.usage.all` (já existente)
 * — mesmo padrão do TeamMcp. Rename pra `project-mgmt.*` vira ADR + migration
 * em etapa futura.
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
                'name'    => 'project_mgmt_module',
                'label'   => 'Project Mgmt',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões expostas no cadastro de papéis (Roles) do UltimatePOS.
     *
     * NÃO declara permissões — as telas reusam `copiloto.mcp.usage.all`.
     */
    public function user_permissions()
    {
        return [];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('ProjectMgmt');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'project_mgmt_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('copiloto.mcp.usage.all');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'project-mgmt';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    'Project Mgmt',
                    function ($sub) {
                        $sub->url(
                            route('project-mgmt.my-work.index'),
                            'My Work + Inbox',
                            [
                                'icon'   => 'fa fas fa-check-square',
                                'active' => request()->segment(2) == 'my-work',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.board.index'),
                            'Board (Kanban)',
                            [
                                'icon'   => 'fa fas fa-columns',
                                'active' => request()->segment(2) == 'board',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.backlog.index'),
                            'Backlog',
                            [
                                'icon'   => 'fa fas fa-list',
                                'active' => request()->segment(2) == 'backlog',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.roadmap.index'),
                            'Roadmap',
                            [
                                'icon'   => 'fa fas fa-calendar-alt',
                                'active' => request()->segment(2) == 'roadmap',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.activity.index'),
                            'Activity feed',
                            [
                                'icon'   => 'fa fas fa-stream',
                                'active' => request()->segment(2) == 'activity',
                            ]
                        );
                        $sub->url(
                            route('project-mgmt.burndown.index'),
                            'Burndown',
                            [
                                'icon'   => 'fa fas fa-chart-line',
                                'active' => request()->segment(2) == 'burndown',
                            ]
                        );
                    },
                    [
                        'icon'   => 'fa fas fa-project-diagram',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(92); // Logo após TeamMcp (91)
            }
        );
    }
}
