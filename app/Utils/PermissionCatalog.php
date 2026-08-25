<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Catálogo FECHADO de permissões aceitáveis ao salvar um papel.
 *
 * POR QUE existe: RoleController::__createPermissionIfNotExists() criava QUALQUER nome que
 * chegasse no POST, e a tabela `permissions` do Spatie é GLOBAL (não tem business_id). Um POST
 * forjado com permissions[] arbitrário poluía o catálogo de TODOS os tenants — e a poluição não
 * era visível em lugar nenhum da UI. Medido no main em 2026-08-19.
 *
 * A fonte do CORE são as views role/create.blade.php e role/edit.blade.php: o backend passa a
 * aceitar exatamente o que o formulário oferece, que é a definição operacional de "fechado".
 * As duas views foram medidas e são idênticas neste ponto: 122 checkbox + 29 radio = 151 em
 * 2026-08-19; 124 + 29 = 153 depois que `commission_agent.view|manage` entraram em 2026-08-20.
 * Quem quiser conferir hoje não lê este número: roda o PermissionCatalogSyncTest.
 *
 * Esta lista é DERIVADA. Se você adicionar um checkbox de permissão nas views, adicione a chave
 * aqui — o RolePermissionCatalogTest compara as duas fontes e QUEBRA no drift, então o
 * esquecimento vira teste vermelho, não brecha silenciosa.
 *
 * Permissões de MÓDULO não entram aqui: são declaradas em tempo de execução por
 * `ModuleUtil::getModuleData('user_permissions')` e entram pelo método permitidas().
 */
class PermissionCatalog
{
    /** Permissões do núcleo, derivadas das views de papel. NÃO editar sem atualizar a view. */
    public const CORE = [
        'access_commission_agent_shipping',
        'access_default_selling_price',
        'access_own_sell_return',
        'access_own_shipping',
        'access_pending_shipments_only',
        'access_printers',
        'access_sell_return',
        'access_shipping',
        'access_tables',
        'access_types_of_service',
        'account.access',
        'all_expense.access',
        'barcode_settings.access',
        'brand.create',
        'brand.delete',
        'brand.update',
        'brand.view',
        'business_settings.access',
        'category.create',
        'category.delete',
        'category.update',
        'category.view',
        'close_cash_register',
        'commission_agent.manage',
        'commission_agent.view',
        'configure_dashboard',
        'contacts_report.view',
        'crud_all_bookings',
        'crud_own_bookings',
        'customer.create',
        'customer.delete',
        'customer.update',
        'customer.view',
        'customer.view_own',
        'customer_irrespective_of_sell',
        'customer_with_no_sell_one_month',
        'customer_with_no_sell_one_year',
        'customer_with_no_sell_six_month',
        'customer_with_no_sell_three_month',
        'dashboard.data',
        'delete_account_transaction',
        'delete_purchase_payment',
        'delete_sell_payment',
        'direct_sell.access',
        'direct_sell.delete',
        'direct_sell.update',
        'direct_sell.view',
        'disable_card',
        'disable_credit_sale',
        'disable_discount',
        'disable_draft',
        'disable_express_checkout',
        'disable_pay_checkout',
        'disable_quotation',
        'disable_suspend_sale',
        'discount.access',
        'draft.delete',
        'draft.update',
        'draft.view_all',
        'draft.view_own',
        'edit_account_transaction',
        'edit_invoice_number',
        'edit_pos_payment',
        'edit_product_discount_from_pos_screen',
        'edit_product_discount_from_sale_screen',
        'edit_product_price_from_pos_screen',
        'edit_product_price_from_sale_screen',
        'edit_purchase_payment',
        'edit_purchase_price',
        'edit_sell_payment',
        'expense.add',
        'expense.delete',
        'expense.edit',
        'expense_report.view',
        'invoice_settings.access',
        'print_invoice',
        'product.create',
        'product.delete',
        'product.opening_stock',
        'product.update',
        'product.view',
        'profit_loss_report.view',
        'purchase.create',
        'purchase.delete',
        'purchase.payments',
        'purchase.update',
        'purchase.update_status',
        'purchase.view',
        'purchase_n_sell_report.view',
        'purchase_order.create',
        'purchase_order.delete',
        'purchase_order.update',
        'purchase_order.view_all',
        'purchase_order.view_own',
        'purchase_requisition.create',
        'purchase_requisition.delete',
        'purchase_requisition.view_all',
        'purchase_requisition.view_own',
        'quotation.delete',
        'quotation.update',
        'quotation.view_all',
        'quotation.view_own',
        'register_report.view',
        'report.stock_details',
        'roles.create',
        'roles.delete',
        'roles.update',
        'roles.view',
        'sale.history.view',
        'sales_representative.view',
        'sell.create',
        'sell.delete',
        'sell.payments',
        'sell.update',
        'sell.view',
        'send_notification',
        'so.create',
        'so.delete',
        'so.update',
        'so.view_all',
        'so.view_own',
        'stock_report.view',
        'supplier.create',
        'supplier.delete',
        'supplier.update',
        'supplier.view',
        'supplier.view_own',
        'tax_rate.create',
        'tax_rate.delete',
        'tax_rate.update',
        'tax_rate.view',
        'tax_report.view',
        'trending_product_report.view',
        'unit.create',
        'unit.delete',
        'unit.update',
        'unit.view',
        'user.create',
        'user.delete',
        'user.update',
        'user.view',
        'view_cash_register',
        'view_commission_agent_sell',
        'view_due_sells_only',
        'view_export_buttons',
        'view_overdue_sells_only',
        'view_own_expense',
        'view_own_purchase',
        'view_own_sell_only',
        'view_paid_sells_only',
        'view_partial_sells_only',
        'view_product_stock_value',
        'view_purchase_price',
    ];

    /**
     * Padrões DINÂMICOS legítimos — a view os gera em LOOP, então não cabem numa lista fixa.
     *
     * Varredura contada das views de papel em 2026-08-19: dos 152 controles, 151 são estáticos
     * (122 `permissions[]` + 29 `radio_option[]`) e exatamente UM é gerado em foreach —
     * `spg_permissions[]` com 'selling_price_group.' . id. Sem esta exceção o catálogo
     * descartaria todo grupo de preço, que é um bug PIOR que o buraco original: silencioso.
     */
    public const DINAMICAS = [
        '/^selling_price_group\.\d+$/',
    ];

    /**
     * Conjunto aceitável = núcleo + o que os módulos ativos declaram.
     *
     * @param  array  $modulePermissions  saída de ModuleUtil::getModuleData('user_permissions')
     * @return array<string,true>  mapa nome => true, para lookup O(1)
     */
    public static function permitidas(array $modulePermissions = []): array
    {
        $mapa = array_fill_keys(self::CORE, true);

        // Estrutura: ['NomeDoModulo' => [['value' => 'x', 'label' => ..., 'default' => bool], ...]]
        foreach ($modulePermissions as $doModulo) {
            if (! is_array($doModulo)) {
                continue;
            }

            foreach ($doModulo as $permissao) {
                if (is_array($permissao) && ! empty($permissao['value']) && is_string($permissao['value'])) {
                    $mapa[$permissao['value']] = true;
                }
            }
        }

        return $mapa;
    }

    /**
     * Nomes recebidos que NÃO pertencem ao catálogo. Vazio = tudo válido.
     *
     * @param  array  $recebidas  o que veio do POST (já achatado)
     * @return array<int,string>  os intrusos, para a mensagem de erro
     */
    public static function intrusas(array $recebidas, array $modulePermissions = []): array
    {
        $permitidas = self::permitidas($modulePermissions);

        $intrusas = [];
        foreach ($recebidas as $nome) {
            if (! is_string($nome) || $nome === '') {
                continue;
            }

            if (isset($permitidas[$nome])) {
                continue;
            }

            if (self::casaPadraoDinamico($nome)) {
                continue;
            }

            $intrusas[] = $nome;
        }

        return array_values(array_unique($intrusas));
    }

    /** O nome casa algum padrão gerado em loop pela view? */
    public static function casaPadraoDinamico(string $nome): bool
    {
        foreach (self::DINAMICAS as $padrao) {
            if (preg_match($padrao, $nome) === 1) {
                return true;
            }
        }

        return false;
    }
}
