<?php

namespace Modules\PaymentGateway\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo PaymentGateway.
 *
 * Descoberto pelo middleware AdminSidebarMenu do core UltimatePOS.
 * Sidebar pattern canon: skill `sidebar-menu-arch`.
 *
 * Onda 1: apenas superadmin_package + user_permissions registrados.
 * Onda 5 (2026-05-19): modifyAdminMenu populado — Page Inertia
 * /settings/payment-gateways entregue na Onda 4d F3 (PR #1135).
 * Permission gate paymentgateway.access (registrada via
 * `php artisan paymentgateway:register-permissions`).
 */
class DataController extends Controller
{
    /**
     * Camada 1 — registro no Superadmin Package.
     * Wagner marca em /superadmin/packages/{id}/edit pra liberar pra business.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'paymentgateway_module',
                'label'   => __('paymentgateway::paymentgateway.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Camada 3 — Permissions Spatie granulares.
     *
     * `paymentgateway.access` é raiz (vê Page + sidebar item).
     * Demais granulares per CONTRACTS.md §7 — separação Tier escalado pra refund.
     */
    public function user_permissions(): array
    {
        return [
            ['value' => 'paymentgateway.access',                  'label' => __('paymentgateway::paymentgateway.permissao_acesso'),                  'default' => false],
            ['value' => 'paymentgateway.credenciais.viewAny',     'label' => __('paymentgateway::paymentgateway.permissao_credenciais_viewAny'),     'default' => false],
            ['value' => 'paymentgateway.credenciais.create',      'label' => __('paymentgateway::paymentgateway.permissao_credenciais_create'),      'default' => false],
            ['value' => 'paymentgateway.credenciais.update',      'label' => __('paymentgateway::paymentgateway.permissao_credenciais_update'),      'default' => false],
            ['value' => 'paymentgateway.credenciais.delete',      'label' => __('paymentgateway::paymentgateway.permissao_credenciais_delete'),      'default' => false],
            ['value' => 'paymentgateway.cobranca.viewAny',        'label' => __('paymentgateway::paymentgateway.permissao_cobranca_viewAny'),        'default' => false],
            ['value' => 'paymentgateway.cobranca.emit',           'label' => __('paymentgateway::paymentgateway.permissao_cobranca_emit'),           'default' => false],
            ['value' => 'paymentgateway.cobranca.cancel',         'label' => __('paymentgateway::paymentgateway.permissao_cobranca_cancel'),         'default' => false],
            ['value' => 'paymentgateway.cobranca.refund',         'label' => __('paymentgateway::paymentgateway.permissao_cobranca_refund'),         'default' => false],
            ['value' => 'paymentgateway.webhook.replay',          'label' => __('paymentgateway::paymentgateway.permissao_webhook_replay'),          'default' => false],
        ];
    }

    /**
     * Camada visual sidebar — Onda 5 (2026-05-19): popular.
     *
     * Adiciona entrada "Gateways de Pagamento" apontando pra
     * /settings/payment-gateways (Page Inertia Onda 4d F3, PR #1135).
     *
     * Pattern segue Financeiro DataController.modifyAdminMenu — guard
     * via isModuleInstalled + permission `paymentgateway.access`.
     * 'group' => 'fin' agrupa visualmente com Financeiro na sidebar
     * (Sidebar.tsx SIDEBAR_GROUPS canon ADR sidebar-menu-arch).
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user() && auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('PaymentGateway');
        } else {
            $business_id = session('user.business_id');
            $is_enabled = $business_id
                ? (bool) $module_util->hasThePermissionInSubscription(
                    $business_id,
                    'paymentgateway_module',
                    'superadmin_package'
                )
                : false;
        }

        if (! $is_enabled) {
            return;
        }

        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('paymentgateway.access')) {
            return;
        }

        $segmento_settings = request()->segment(1) === 'settings'
            && request()->segment(2) === 'payment-gateways';

        Menu::modify('admin-sidebar-menu', function ($menu) use ($segmento_settings) {
            // ADR 0180 Fase 4 Wave D FINANÇAS+PESSOAS (2026-05-21): entry é ghost
            // do módulo principal Financeiro (G F) — sem shortcut próprio. Primary
            // "Conectar gateway" + ghost single-element pra mante shape contratual
            // (Wave A ProductCatalogue precedent — Sidebar.tsx aceita array 1-elem).
            // group: 'fin' legacy preservado — LEGACY_GROUP_MAP cobre fin→financas v3.
            $menu->url(
                url('/settings/payment-gateways'),
                __('paymentgateway::paymentgateway.module_label'),
                [
                    'icon'    => 'fa fas fa-key',
                    'active'  => $segmento_settings,
                    'group'   => 'fin',
                    'primary' => [
                        'label'    => 'Conectar gateway',
                        'href'     => '/settings/payment-gateways',
                        'shortcut' => 'N',
                    ],
                    'ghosts'  => [
                        ['key' => 'payment-gateways', 'label' => 'Gateways de Pagamento', 'href' => '/settings/payment-gateways'],
                    ],
                ]
            )->order(85.4);
        });
    }
}
