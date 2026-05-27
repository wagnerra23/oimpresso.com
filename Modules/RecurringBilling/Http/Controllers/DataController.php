<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Menu;

/**
 * DataController do módulo RecurringBilling — sidebar AppShellV2.
 *
 * Descoberto pelo middleware `AdminSidebarMenu` do core UltimatePOS.
 * Sidebar pattern canon: skill `sidebar-menu-arch`. Item agrupado visualmente
 * em SIDEBAR_GROUPS['fin'] (FINANCEIRO) via lookup label literal `Cobrança
 * Recorrente` em resources/js/Components/cockpit/Sidebar.tsx.
 *
 * Order 86 — entre Financeiro (85) e PontoWr2 (88).
 *
 * Ativado 2026-05-17 (Ondas 3+4+5 — primeiro Page Inertia).
 * Onda 10 v9,75: permissões granulares Spatie + remoção guard SUPERADMIN-only
 * (agora qualquer user com `recurringbilling.access` vê o item).
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

    /**
     * Permissions Spatie granulares — Onda 10 v9,75.
     *
     * `recurringbilling.access` é a raiz (ver Page + sidebar item).
     * Demais são granulares pra evolução pós-canary G1 Martinho.
     */
    public function user_permissions(): array
    {
        return [
            ['value' => 'recurringbilling.access',                    'label' => __('recurringbilling::recurringbilling.permissao_acesso'),                    'default' => false],
            ['value' => 'recurringbilling.subscriptions.manage',      'label' => __('recurringbilling::recurringbilling.permissao_subscriptions_manage'),      'default' => false],
            ['value' => 'recurringbilling.subscriptions.cancel',      'label' => __('recurringbilling::recurringbilling.permissao_subscriptions_cancel'),      'default' => false],
            ['value' => 'recurringbilling.plans.manage',              'label' => __('recurringbilling::recurringbilling.permissao_plans_manage'),              'default' => false],
            ['value' => 'recurringbilling.invoices.view',             'label' => __('recurringbilling::recurringbilling.permissao_invoices_view'),             'default' => false],
            ['value' => 'recurringbilling.invoices.charge',           'label' => __('recurringbilling::recurringbilling.permissao_invoices_charge'),           'default' => false],
            ['value' => 'recurringbilling.notes.write',               'label' => __('recurringbilling::recurringbilling.permissao_notes_write'),               'default' => false],
            ['value' => 'recurringbilling.favorites.write',           'label' => __('recurringbilling::recurringbilling.permissao_favorites_write'),           'default' => false],
            ['value' => 'recurringbilling.configuracoes.manage',      'label' => __('recurringbilling::recurringbilling.permissao_configuracoes_manage'),      'default' => false],
        ];
    }

    /**
     * Injeta item "Cobrança Recorrente" no sidebar AppShellV2.
     *
     * Guards canônicos (skill sidebar-menu-arch):
     *  1. Módulo instalado (superadmin via isModuleInstalled, demais via subscription)
     *  2. Rota nomeada existe (defesa contra deploy parcial)
     *  3. Usuário tem `recurringbilling.access` OR superadmin (Onda 10 v9,75 —
     *     antes era SUPERADMIN-only).
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

        // Onda 10 v9,75 — pós-cutover. Antes era SUPERADMIN-only.
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('recurringbilling.access')) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#ffd6a5' : '';
        $segmento_ativo = request()->segment(1) === 'recurring-billing';

        // Wagner 2026-05-22: Cobrança Recorrente FICA no sidebar como entry
        // própria. Revertido erro do PR #1399 (havia movido pra ghost do
        // Financeiro, mas Wagner esclareceu que deve permanecer entry separada).
        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->url(
                    route('recurring-billing.index'),
                    'Cobrança Recorrente',
                    [
                        'icon'    => 'fa fas fa-sync-alt',
                        'style'   => 'background-color:' . $background_color,
                        'active'  => $segmento_ativo,
                        'group'   => 'financas',
                        'primary' => [
                            'label'    => 'Nova assinatura',
                            'href'     => '/recurring-billing/create',
                            'shortcut' => 'N',
                        ],
                        'ghosts'  => [
                            ['key' => 'recurring-billing', 'label' => 'Assinaturas',    'href' => '/recurring-billing'],
                            // Wagner 2026-05-22 P1: +3 ghosts pra zerar órfãs RecurringBilling.
                            ['key' => 'planos',            'label' => 'Planos',         'href' => '/recurring-billing/planos'],
                            ['key' => 'faturas',           'label' => 'Faturas',        'href' => '/recurring-billing/faturas'],
                            ['key' => 'configuracoes',     'label' => 'Configurações',  'href' => '/recurring-billing/configuracoes'],
                        ],
                    ]
                )->order(86);
            }
        );
    }
}
