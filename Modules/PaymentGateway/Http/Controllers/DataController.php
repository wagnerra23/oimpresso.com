<?php

namespace Modules\PaymentGateway\Http\Controllers;

use Illuminate\Routing\Controller;

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
     * Camada visual sidebar — Wagner 2026-05-26: NO-OP.
     *
     * Gateway de Pagamento NÃO é mais entry própria do sidebar. Vive como
     * GHOST da entry "Cobrança" (definido em Modules/Financeiro/Http/Controllers/
     * DataController.modifyAdminMenu, ghost key='gateway' → /settings/payment-gateways).
     *
     * Justificativa Wagner 2026-05-26:
     *  - Grupo FINANÇAS = 4 entries flat canon (Caixa · Cobrança · Financeiro ·
     *    Cobrança Recorrente). Gateway é CONFIGURAÇÃO de cobrança, não fluxo
     *    operacional diário — não merece entry top-level.
     *  - Larissa (biz=4 cliente piloto) acessa via PageHeader da Cobrança
     *    quando configura Asaas/Inter/BCB pix.
     *  - Reduz ruído visual no sidebar (FINANÇAS tinha 3 entries antes do PR;
     *    agora tem 4 entries semanticamente paralelas).
     *
     * Rotas /settings/payment-gateways CONTINUAM ativas — apenas a injeção
     * sidebar foi desligada. Acesso via URL direta, ghost na Cobrança, ou
     * Cmd+K palette.
     *
     * Histórico do método (Onda 5 PR #1135 → 2026-05-26):
     *   PR #1135 — Onda 5: criada entrada "Gateways de Pagamento" order 85.4
     *   2026-05-22 — fix label hardcoded i18n bug
     *   2026-05-26 — DESLIGADA (Wagner direção: vira ghost da Cobrança)
     */
    public function modifyAdminMenu(): void
    {
        // No-op intencional. Ver docblock acima.
    }
}
