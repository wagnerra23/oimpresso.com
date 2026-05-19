<?php

namespace Modules\PaymentGateway\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * DataController do módulo PaymentGateway — Onda 1 esqueleto.
 *
 * Descoberto pelo middleware AdminSidebarMenu do core UltimatePOS.
 * Sidebar pattern canon: skill `sidebar-menu-arch`.
 *
 * Onda 1 (esta): apenas superadmin_package + user_permissions registrados
 * pra que Wagner possa habilitar nas 3 camadas (subscription package +
 * business.enabled_modules + Spatie permissions) antes de Onda 4 entregar
 * Page Inertia "Cobrança" no sidebar.
 *
 * modifyAdminMenu vazio nesta onda — ativa em Onda 4 quando tela existir.
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
     * Camada visual sidebar — Onda 1 vazio. Onda 4 ativa quando Page Cobrança nascer.
     */
    public function modifyAdminMenu(): void
    {
        // intencionalmente vazio — vide docblock.
    }
}
