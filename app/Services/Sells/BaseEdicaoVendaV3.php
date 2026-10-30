<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Transaction;

/** Dados que o update sobrescreve mesmo quando a V3 não oferece um controle para eles. */
final class BaseEdicaoVendaV3
{
    /** @return array<string, mixed> */
    public function daVenda(Transaction $venda): array
    {
        $campos = [
            'status', 'invoice_no', 'contact_id', 'location_id', 'customer_group_id',
            'discount_type', 'discount_amount', 'additional_notes', 'staff_note', 'commission_agent',
            'shipping_details', 'shipping_address', 'shipping_status', 'shipping_charges',
            'customer_secondary_address', 'delivered_to', 'delivery_person', 'exchange_rate',
            'selling_price_group_id', 'pay_term_number', 'pay_term_type', 'is_suspend',
            'is_recurring', 'recur_interval', 'recur_interval_type', 'subscription_repeat_on',
            'recur_repetitions', 'order_addresses', 'rp_redeemed', 'rp_redeemed_amount',
            'types_of_service_id', 'vehicle_id', 'packing_charge', 'packing_charge_type',
            'round_off_amount', 'res_table_id', 'res_waiter_id', 'sales_order_ids',
            'prefer_payment_method', 'prefer_payment_account', 'is_export', 'export_custom_fields_info',
        ];
        foreach (['custom_field' => 4, 'shipping_custom_field' => 5, 'service_custom_field' => 6,
            'additional_expense_value' => 4, 'additional_expense_key' => 4] as $prefixo => $limite) {
            for ($i = 1; $i <= $limite; $i++) {
                $campos[] = "{$prefixo}_{$i}";
            }
        }
        // Só atributos selecionados, com seus casts. Serializar o modelo inteiro invocaria
        // accessors como due_date, enquanto edit() já formatou transaction_date para a tela.
        // Também não acessa métodos homônimos de atributos ausentes em um modelo parcial.
        $base = [];
        foreach (array_intersect_key($venda->getAttributes(), array_flip($campos)) as $campo => $_) {
            $base[$campo] = $venda->getAttribute($campo);
        }
        $base['sale_note'] = $venda->additional_notes;
        $base['tax_rate_id'] = $venda->tax_id;
        $base['price_group'] = $venda->selling_price_group_id;
        // O update normaliza quotation/proforma antes de salvar; status draft sozinho apagaria isso.
        if ($venda->status === 'draft' && ($venda->is_quotation || $venda->sub_status === 'quotation')) {
            $base['status'] = 'quotation';
        } elseif ($venda->status === 'draft' && $venda->sub_status === 'proforma') {
            $base['status'] = 'proforma';
        }
        // Só os campos que updateSellTransaction lê com num_uf. Sem cálculo nem arredondamento:
        // DECIMAL cru do banco -> vírgula decimal, sem passar por float (incidente num_uf).
        foreach (['discount_amount', 'shipping_charges', 'exchange_rate', 'packing_charge', 'additional_expense_value_1',
            'additional_expense_value_2', 'additional_expense_value_3', 'additional_expense_value_4'] as $campo) {
            $base[$campo] = str_replace('.', ',', (string) ($venda->getRawOriginal($campo) ?? '0'));
        }

        return $base;
    }
}
