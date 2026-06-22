<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Defines module as a superadmin package.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'officeimpresso_module',
                'label' => __('officeimpresso::lang.officeimpresso_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no UI de Roles do UltimatePOS.
     *
     * Adicionado 2026-04-26 (audit DataController). Officeimpresso é
     * superadmin-only por design, mas registramos permissão pra aparecer
     * no UI de Roles (auditoria) e permitir delegação futura.
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'officeimpresso.access',
                'label' => __('officeimpresso::lang.officeimpresso_module'),
                'default' => false,
            ],
            [
                // Delegável a um login próprio de funcionário SEM abrir o
                // Financeiro (gated por `superadmin`). Cobre ver a lista e
                // criar/liberar a credencial OAuth do Delphi. ClientController
                // destroy()/regenerate() seguem superadmin-only.
                'value' => 'officeimpresso.clientes.liberar',
                'label' => 'Office Impresso: liberar clientes (credenciais Delphi)',
                'default' => false,
            ],
        ];
    }

    /**
     * Adds Officeimpresso menus a sidebar admin.
     *
     * Superadmin vê a gestão completa (Computadores + Clientes + Licenças +
     * Logs). Quem tem só a permissão delegada `officeimpresso.clientes.liberar`
     * (ex.: atendente com login próprio) vê APENAS o atalho de Clientes — sem
     * abrir o Financeiro. Ordem 2 (logo depois de Superadmin order 1).
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        if (! auth()->check()) {
            return;
        }

        $isSuperadmin = auth()->user()->can('superadmin');
        $canLiberarClientes = auth()->user()->can('officeimpresso.clientes.liberar');

        if (! $isSuperadmin && ! $canLiberarClientes) {
            return;
        }

        $module_util = new ModuleUtil();
        if (! $module_util->isModuleInstalled('Officeimpresso')) {
            return;
        }

        // ADR 0180 Fase 4 Wave E — Officeimpresso é ghost virtual de Plataforma
        // no grupo canon `sistema` v3. `primary` = "Novo cliente"; os `ghosts`
        // variam por nível: superadmin vê tudo, delegado vê só Clientes.
        if ($isSuperadmin) {
            $baseUrl = action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores']);
            $ghosts = [
                ['key' => 'computadores',       'label' => 'Computadores', 'href' => '/officeimpresso/computadores'],
                ['key' => 'client',             'label' => 'Clientes',     'href' => '/officeimpresso/client'],
                ['key' => 'licenca_computador', 'label' => 'Licenças',     'href' => '/officeimpresso/licenca_computador'],
                ['key' => 'licenca_log',        'label' => 'Logs',         'href' => '/officeimpresso/licenca_log'],
            ];
        } else {
            $baseUrl = '/officeimpresso/client';
            $ghosts = [
                ['key' => 'client', 'label' => 'Clientes', 'href' => '/officeimpresso/client'],
            ];
        }

        Menu::modify('admin-sidebar-menu', function ($menu) use ($baseUrl, $ghosts) {
            $menu->url(
                $baseUrl,
                __('officeimpresso::lang.officeimpresso'),
                [
                    'icon'    => 'fa fas fa-plug',
                    'active'  => request()->segment(1) == 'officeimpresso',
                    'primary' => [
                        'label'    => 'Novo cliente',
                        'href'     => '/officeimpresso/client/create',
                        'shortcut' => 'N',
                    ],
                    'ghosts'  => $ghosts,
                ]
            )->order(2);
        });
    }
}
