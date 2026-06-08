<?php

namespace Modules\ConsultaOs\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Modules/ConsultaOs (portal publico de consulta de OS).
 *
 * Convencao UltimatePOS: middleware AdminSidebarMenu chama
 * Modules\ConsultaOs\Http\Controllers\DataController@modifyAdminMenu em cada request
 * da sidebar admin. Os hooks superadmin_package/user_permissions sao chamados
 * na tela de Roles/Packages.
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'consultaos_module',
                'label'   => 'Modulo Consulta de OS (portal publico)',
                'default' => true,
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'consultaos.access',
                'label'   => 'Consulta OS: acessar atalhos no admin',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('ConsultaOs');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'consultaos_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('consultaos.access');

        if (! $usuario_pode_ver) {
            return;
        }

        // Removido da sidebar — aparece apenas no front-end (topnav/portal público).
    }
}
