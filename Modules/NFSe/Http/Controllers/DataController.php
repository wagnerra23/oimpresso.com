<?php

namespace Modules\NFSe\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo NFSe.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\NFSe\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * @see memory/09-modulos-ultimatepos.md — padrão DataController + sidebar
 * @see Modules/NfeBrasil/Http/Controllers/DataController.php — template
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para Superadmin > Packages.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'nfse_module',
                'label'   => 'Módulo NFSe',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem na tela de Roles do UltimatePOS.
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'nfse.view',
                'label'   => 'NFSe: visualizar notas',
                'default' => false,
            ],
            [
                'value'   => 'nfse.emit',
                'label'   => 'NFSe: emitir nota fiscal de serviço',
                'default' => false,
            ],
            [
                'value'   => 'nfse.cancel',
                'label'   => 'NFSe: cancelar nota fiscal',
                'default' => false,
            ],
            [
                'value'   => 'nfse.settings',
                'label'   => 'NFSe: gerenciar configurações fiscais',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('NFSe');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'nfse_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('nfse.view')
            || auth()->user()->can('nfse.emit');

        if (! $usuario_pode_ver) {
            return;
        }

        // Wagner 2026-05-22: NFSe entry REMOVIDA do sidebar. Hub canon "Fiscal"
        // (Modules/NfeBrasil) cobre NF-e + NFSe via tabs internas na tela
        // /nfebrasil. NFSe continua acessível via URL direta /nfse (rotas
        // intactas) e em ghost futuro do hub Fiscal quando unificarmos telas.
        //
        // Pattern espelha Modules/Fiscal e Modules/RecurringBilling (anteriores).
    }
}
