<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Menu;

/**
 * DataController do módulo RecurringBilling — sidebar AppShellV2.
 *
 * Descoberto pelo middleware `AdminSidebarMenu` do core UltimatePOS
 * (convenção: Modules\<Nome>\Http\Controllers\DataController@modifyAdminMenu).
 *
 * Sidebar pattern canon: skill `sidebar-menu-arch`. Item agrupado visualmente
 * em SIDEBAR_GROUPS['fin'] (FINANCEIRO) via lookup label literal `Cobrança
 * Recorrente` em resources/js/Components/cockpit/Sidebar.tsx.
 *
 * Order 86 — entre Financeiro (85) e PontoWr2 (88).
 * Ativado 2026-05-17 (Ondas 3+4+5 — primeiro Page Inertia
 * Pages/RecurringBilling/Index.tsx).
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'recurringbilling_module',
                'label'   => __('recurringbilling::recurringbilling.module_label'),
                'default' => false,
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'recurringbilling.access',
                'label'   => __('recurringbilling::recurringbilling.permissao_acesso'),
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta item "Cobrança Recorrente" no sidebar AppShellV2.
     *
     * Guards canônicos (skill sidebar-menu-arch):
     *  1. Módulo instalado (superadmin via isModuleInstalled, demais via subscription)
     *  2. Rota nomeada existe (defesa contra deploy parcial — pattern Route::has)
     *  3. SUPERADMIN-only enquanto em construção (espelha Modules/Financeiro
     *     DataController.php padrão Wagner 2026-04-25)
     *
     * Quando módulo virar produção, trocar guard 3 pra:
     *   auth()->user()->can('superadmin') || auth()->user()->can('recurringbilling.access')
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('RecurringBilling');
        } else {
            $business_id = session('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'recurringbilling_module',
                'superadmin_package'
            );
        }
        if (! $is_enabled) {
            return;
        }

        if (! Route::has('recurring-billing.index')) {
            return;
        }

        if (! auth()->user()->can('superadmin')) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#ffd6a5' : '';
        $segmento_ativo = request()->segment(1) === 'recurring-billing';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->url(
                    route('recurring-billing.index'),
                    'Cobrança Recorrente',
                    [
                        'icon'   => 'fa fas fa-sync-alt',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(86);
            }
        );
    }
}
