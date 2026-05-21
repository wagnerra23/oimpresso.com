<?php

namespace Modules\Auditoria\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Modules/Auditoria (governanca transversal).
 *
 * Convencao UltimatePOS: middleware AdminSidebarMenu chama
 * Modules\Auditoria\Http\Controllers\DataController@modifyAdminMenu em cada
 * request da sidebar admin. Hooks superadmin_package/user_permissions sao
 * chamados na tela de Roles/Packages.
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'auditoria_module',
                'label'   => 'Modulo Auditoria (active_log + undo)',
                'default' => true,
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'auditoria.view',
                'label'   => 'Auditoria: ver historico de alteracoes',
                'default' => false,
            ],
            [
                'value'   => 'auditoria.revert.own',
                'label'   => 'Auditoria: reverter acoes proprias (<=24h)',
                'default' => false,
            ],
            [
                'value'   => 'auditoria.revert.any',
                'label'   => 'Auditoria: reverter qualquer acao (<=30d)',
                'default' => false,
            ],
            [
                'value'   => 'auditoria.revert.unlimited',
                'label'   => 'Auditoria: reverter sem limite (superadmin)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Auditoria');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'auditoria_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('auditoria.view');

        if (! $usuario_pode_ver) {
            return;
        }

        $segmento_ativo = request()->segment(1) === 'auditoria';

        Menu::modify('admin-sidebar-menu', function ($menu) use ($segmento_ativo) {
            // ADR 0180 Fase 4 Wave E — Auditoria é ghost virtual de Governança
            // no grupo canon `sistema` v3. Sem `shortcut` (acoplado em Governança);
            // sem `primary` (auditoria é read-only/revert — não há ação de criar);
            // sem `ghosts` (única sub-view canônica: timeline /auditoria; detalhe
            // /auditoria/{id} é drill-down, não navegação ARIA-tab).
            $menu->url(url('/auditoria'), 'Auditoria', [
                'icon'   => 'fa fa-shield',
                'active' => $segmento_ativo,
            ]);
        });
    }
}
