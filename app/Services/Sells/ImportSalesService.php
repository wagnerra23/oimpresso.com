<?php

declare(strict_types=1);

namespace App\Services\Sells;

use App\Business;
use App\Contact;
use App\Product;
use App\TaxRate;
use App\Transaction;
use App\TypesOfService;
use App\Unit;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importação de vendas por planilha — o núcleo que vivia em métodos privados do
 * `ImportSalesController` (`__parseData` · `__formatSaleData` · `__importSales` ·
 * `__importFields`).
 *
 * POR QUE SAIU DO CONTROLLER (thread 05 do playbook de Vendas, decisão D2 de [W] em
 * 2026-10-02): planilha grande passa a ir para a fila, e um Job não chama método privado
 * de controller. O cálculo é o MESMO nos dois caminhos — request e fila — porque os dois
 * chamam esta classe. Foi movido, não reescrito: a conta de total, imposto, desconto,
 * unidade e baixa de estoque é a do legado, linha a linha.
 *
 * O QUE MUDOU DE FATO (e só isto):
 *  1. Tudo o que o legado lia de `session()`/`auth()` vem por parâmetro: business, usuário,
 *     método de custeio. Em fila não há sessão (proibicoes.md, Multi-tenant).
 *  2. Busca por SKU e por unidade passou a ser escopada pelo business (ADR 0093). O legado
 *     fazia `Variation::where('sub_sku', …)` e `Unit::where('actual_name', …)` sem
 *     business: um SKU igual noutro negócio importava o produto DO OUTRO negócio, e uma
 *     unidade homônima trazia o multiplicador da unidade alheia — que entra na quantidade
 *     e, por ela, no total. Só muda o resultado quando havia colisão entre negócios, que é
 *     exatamente o caso errado.
 *
 * REGRA MESTRE (valor/estoque): coberta por tests/Feature/Sells/ImportSalesContratoTest.php,
 * que importa a mesma planilha pelo request e pela fila e confere totais e saldo contra a
 * conta feita à mão.
 */
class ImportSalesService
{
    public function __construct(
        private readonly ProductUtil $productUtil,
        private readonly BusinessUtil $businessUtil,
        private readonly TransactionUtil $transactionUtil,
        private readonly ModuleUtil $moduleUtil,
    ) {
    }

    /**
     * Linhas de dados acima das quais a importação vai para a fila (D2).
     *
     * Ponto de partida, não medição: cada linha faz de 3 a 6 consultas e cada venda
     * criada mais umas 15 (transação, linhas, estoque, custo FIFO, título financeiro).
     * 200 linhas fica longe do teto de tempo do request no shared hosting. Ajustável por
     * `SELLS_IMPORT_LIMITE_SINCRONO` sem deploy de código.
     */
    public function limiteSincrono(): int
    {
        return max(0, (int) config('sells.import.limite_sincrono', 200));
    }

    /**
     * Campos importáveis (R1 do charter): 14, mais 5 quando tipos de serviço está ligado.
     *
     * @return array<string, array{label:string, instruction?:string}>
     */
    public function campos(int $businessId): array
    {
        $fields = [
            'invoice_no' => ['label' => __('sale.invoice_no')],
            'customer_name' => ['label' => __('sale.customer_name')],
            'customer_phone_number' => ['label' => __('lang_v1.customer_phone_number'), 'instruction' => __('lang_v1.either_cust_email_or_phone_required')],
            'customer_email' => ['label' => __('lang_v1.customer_email'), 'instruction' => __('lang_v1.either_cust_email_or_phone_required')],
            'date' => ['label' => __('sale.sale_date'), 'instruction' => __('lang_v1.date_format_instruction')],
            'product' => ['label' => __('product.product_name'), 'instruction' => __('lang_v1.either_product_name_or_sku_required')],
            'sku' => ['label' => __('lang_v1.product_sku'), 'instruction' => __('lang_v1.either_product_name_or_sku_required')],
            'quantity' => ['label' => __('lang_v1.quantity'), 'instruction' => __('lang_v1.required')],
            'unit' => ['label' => __('lang_v1.product_unit')],
            'unit_price' => ['label' => __('sale.unit_price')],
            'item_tax' => ['label' => __('lang_v1.item_tax')],
            'item_discount' => ['label' => __('lang_v1.item_discount')],
            'item_description' => ['label' => __('lang_v1.item_description')],
            'order_total' => ['label' => __('lang_v1.order_total')],
        ];

        if ($this->moduleUtil->isModuleEnabled('types_of_service', $businessId)) {
            $fields['types_of_service'] = ['label' => __('lang_v1.types_of_service')];
            $fields['service_custom_field1'] = ['label' => __('lang_v1.service_custom_field_1')];
            $fields['service_custom_field2'] = ['label' => __('lang_v1.service_custom_field_2')];
            $fields['service_custom_field3'] = ['label' => __('lang_v1.service_custom_field_3')];
            $fields['service_custom_field4'] = ['label' => __('lang_v1.service_custom_field_4')];
        }

        return $fields;
    }

    /**
     * Pré-mapeamento da prévia (R2): coluna recebe o campo de rótulo mais parecido,
     * se a semelhança for de 50% ou mais.
     *
     * @param  array<int, mixed>  $headers
     * @param  array<string, string>  $rotulos  campo => rótulo
     * @return array<int, string|null>
     */
    public function preMapear(array $headers, array $rotulos): array
    {
        $match_array = [];
        foreach ($headers as $key => $value) {
            $match_percentage = [];
            foreach ($rotulos as $k => $v) {
                similar_text((string) $value, (string) $v, $percentage);
                $match_percentage[$k] = $percentage;
            }
            $max_key = array_keys($match_percentage, max($match_percentage))[0];

            $match_array[$key] = $match_percentage[$max_key] >= 50 ? $max_key : null;
        }

        return $match_array;
    }

    /**
     * Lê a planilha. Linha 0 = cabeçalho (colunas vazias removidas); as demais trazem só
     * as colunas que têm cabeçalho.
     *
     * @return array<int, array<int, mixed>>
     */
    public function lerPlanilha(string $caminhoAbsoluto): array
    {
        // `new \stdClass` e não `[]` (o que o legado passava): o facade declara `object
        // $import` e o Reader só consulta o argumento por `instanceof` — mesmo comportamento
        // em runtime, tipo certo (mesma troca do ImportarPresencaJob).
        $array = Excel::toArray(new \stdClass, $caminhoAbsoluto)[0];

        //remove blank columns from headers
        $headers = array_filter($array[0]);

        //Remove header row
        unset($array[0]);
        $parsed_array[] = $headers;
        foreach ($array as $row) {
            $temp = [];
            foreach ($row as $k => $v) {
                if (array_key_exists($k, $headers)) {
                    $temp[] = $v;
                }
            }
            $parsed_array[] = $temp;
        }

        return $parsed_array;
    }

    /**
     * Valida linha a linha (R3/R6) e agrupa por `group_by` (R4). Cada grupo vira uma venda.
     *
     * @param  array<int, array<int, mixed>>  $imported_data  linhas SEM o cabeçalho
     * @param  array<int|string, string|null>  $import_fields  coluna => campo
     * @return array<string|int, array<int, array<string, mixed>>>
     */
    public function formatar(array $imported_data, array $import_fields, $group_by): array
    {
        $formatted_array = [];
        $invoice_number_key = array_search('invoice_no', $import_fields);
        $customer_name_key = array_search('customer_name', $import_fields);
        $customer_phone_key = array_search('customer_phone_number', $import_fields);
        $customer_email_key = array_search('customer_email', $import_fields);
        $date_key = array_search('date', $import_fields);
        $product_key = array_search('product', $import_fields);
        $sku_key = array_search('sku', $import_fields);
        $quantity_key = array_search('quantity', $import_fields);
        $unit_price_key = array_search('unit_price', $import_fields);
        $item_tax_key = array_search('item_tax', $import_fields);
        $item_discount_key = array_search('item_discount', $import_fields);
        $item_description_key = array_search('item_description', $import_fields);
        $order_total_key = array_search('order_total', $import_fields);
        $unit_key = array_search('unit', $import_fields);
        $tos_key = array_search('types_of_service', $import_fields);
        $service_custom_field1_key = array_search('service_custom_field1', $import_fields);
        $service_custom_field2_key = array_search('service_custom_field2', $import_fields);
        $service_custom_field3_key = array_search('service_custom_field3', $import_fields);
        $service_custom_field4_key = array_search('service_custom_field4', $import_fields);

        $row_index = 2;
        foreach ($imported_data as $key => $value) {
            $formatted_array[$key]['invoice_no'] = $invoice_number_key !== false ? $value[$invoice_number_key] : null;
            $formatted_array[$key]['customer_name'] = $customer_name_key !== false ? $value[$customer_name_key] : null;
            $formatted_array[$key]['customer_phone_number'] = $customer_phone_key !== false ? $value[$customer_phone_key] : null;
            $formatted_array[$key]['customer_email'] = $customer_email_key !== false ? $value[$customer_email_key] : null;
            $formatted_array[$key]['date'] = $date_key !== false ? $value[$date_key] : null;
            $formatted_array[$key]['product'] = $product_key !== false ? $value[$product_key] : null;
            $formatted_array[$key]['sku'] = $sku_key !== false ? $value[$sku_key] : null;
            $formatted_array[$key]['quantity'] = $quantity_key !== false ? $value[$quantity_key] : null;
            $formatted_array[$key]['unit_price'] = $unit_price_key !== false ? $value[$unit_price_key] : null;
            $formatted_array[$key]['item_tax'] = $item_tax_key !== false ? $value[$item_tax_key] : null;
            $formatted_array[$key]['item_discount'] = $item_discount_key !== false ? $value[$item_discount_key] : null;
            $formatted_array[$key]['item_description'] = $item_description_key !== false ? $value[$item_description_key] : null;
            $formatted_array[$key]['order_total'] = $order_total_key !== false ? $value[$order_total_key] : null;
            $formatted_array[$key]['unit'] = $unit_key !== false ? $value[$unit_key] : null;
            $formatted_array[$key]['types_of_service'] = $tos_key !== false ? $value[$tos_key] : null;
            $formatted_array[$key]['service_custom_field1'] = $service_custom_field1_key !== false ? $value[$service_custom_field1_key] : null;
            $formatted_array[$key]['service_custom_field2'] = $service_custom_field2_key !== false ? $value[$service_custom_field2_key] : null;
            $formatted_array[$key]['service_custom_field3'] = $service_custom_field3_key !== false ? $value[$service_custom_field3_key] : null;
            $formatted_array[$key]['service_custom_field4'] = $service_custom_field4_key !== false ? $value[$service_custom_field4_key] : null;
            $formatted_array[$key]['group_by'] = $value[$group_by];

            //check empty
            if (empty($formatted_array[$key]['customer_phone_number']) && empty($formatted_array[$key]['customer_email'])) {
                throw new \Exception(__('lang_v1.email_or_phone_cannot_be_empty_in_row', ['row' => $row_index]));
            }
            if (empty($formatted_array[$key]['product']) && empty($formatted_array[$key]['sku'])) {
                throw new \Exception(__('lang_v1.product_cannot_be_empty_in_row', ['row' => $row_index]));
            }
            if (empty($formatted_array[$key]['quantity'])) {
                throw new \Exception(__('lang_v1.quantity_cannot_be_empty_in_row', ['row' => $row_index]));
            }
            if (empty($formatted_array[$key]['unit_price'])) {
                throw new \Exception(__('lang_v1.unit_price_cannot_be_empty_in_row', ['row' => $row_index]));
            }

            $row_index++;
        }
        $formatted_data = [];
        foreach ($formatted_array as $array) {
            $formatted_data[$array['group_by']][] = $array;
        }

        return $formatted_data;
    }

    /**
     * Grava as vendas de um lote. NÃO abre transação de banco — quem chama abre (request
     * e Job abrem uma só, envolvendo o lote inteiro: ou entra tudo, ou nada).
     *
     * @param  array<string|int, array<int, array<string, mixed>>>  $formated_data  saída de formatar()
     * @param  (callable(int, int): void)|null  $aoProgredir  chamado a cada venda gravada (feitas, total)
     * @return array{lote:int, vendas:int}
     */
    public function importar(
        array $formated_data,
        int $business_id,
        int $user_id,
        int $location_id,
        ?callable $aoProgredir = null,
    ): array {
        // Próximo lote do negócio: 1 se nunca importou (max = null), senão max + 1.
        $import_batch = ((int) Transaction::where('business_id', $business_id)->max('import_batch')) + 1;

        $business_model = Business::findOrFail($business_id);
        $is_types_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service', $business_id);

        $now = Carbon::now()->toDateTimeString();
        $row_index = 2;
        $total_vendas = count($formated_data);
        $feitas = 0;
        foreach ($formated_data as $data) {
            $order_total = 0;
            $sell_lines = [];
            foreach ($data as $line_data) {
                if (! empty($line_data['sku'])) {
                    // Escopo de business (ADR 0093): o legado buscava o SKU no banco inteiro.
                    $variation = Variation::join('products as imp_produto', 'imp_produto.id', '=', 'variations.product_id')
                                    ->where('imp_produto.business_id', $business_id)
                                    ->where('variations.sub_sku', $line_data['sku'])
                                    ->select('variations.*')
                                    ->first();

                    $product = ! empty($variation) ? Product::find($variation->product_id) : null;
                } else {
                    $product = Product::where('business_id', $business_id)
                                    ->where('name', $line_data['product'])
                                    ->first();
                    // = `$product->variations->first()` do legado (relação sem ordem → PK).
                    $variation = ! empty($product) ? Variation::where('product_id', $product->id)->orderBy('id')->first() : null;
                }

                if (empty($variation)) {
                    throw new \Exception(__('lang_v1.import_sale_product_not_found', ['row' => $row_index, 'product_name' => $line_data['product'], 'sku' => $line_data['sku']]));
                }

                $tax_id = null;
                $item_tax = 0;
                $line_discount = ! empty($line_data['item_discount']) ? $line_data['item_discount'] : 0;

                $unit_price = $line_data['unit_price'];

                $price_before_tax = $line_data['unit_price'] - $line_discount;
                $price_inc_tax = $price_before_tax;
                if (! empty($line_data['item_tax'])) {
                    $tax = TaxRate::where('business_id', $business_id)
                                ->where('name', $line_data['item_tax'])
                                ->first();

                    if (empty($tax)) {
                        throw new \Exception(__('lang_v1.import_sale_tax_not_found', ['row' => $row_index, 'tax_name' => $line_data['item_tax']]));
                    }
                    $tax_id = $tax->id;
                    // Movido do controller sem tocar: o docblock de calc_percentage diz `int`, mas a
                    // alíquota é decimal e o cálculo sempre recebeu float. Truncar mudaria o imposto.
                    $item_tax = $this->transactionUtil->calc_percentage($price_before_tax, $tax->amount); // @phpstan-ignore argument.type
                    $price_inc_tax = $price_before_tax + $item_tax;
                }

                //check if date is correct
                if (! empty($line_data['date'])) {
                    try {
                        Carbon::parse($line_data['date']);
                    } catch (\Exception $e) {
                        throw new \Exception(__('lang_v1.invalid_date_format_at', ['row' => $row_index]));
                    }
                }

                $temp = [
                    'product_id' => $variation->product_id,
                    'variation_id' => $variation->id,
                    'quantity' => $line_data['quantity'],
                    'unit_price' => $unit_price,
                    'unit_price_inc_tax' => $price_inc_tax,
                    'line_discount_type' => 'fixed',
                    'line_discount_amount' => $line_discount,
                    'item_tax' => $item_tax,
                    'tax_id' => $tax_id,
                    'sell_line_note' => $line_data['item_description'],
                    'product_unit_id' => $product->unit_id,
                    'enable_stock' => $product->enable_stock,
                    'type' => $product->type,
                    'combo_variations' => $product->type == 'combo' ? $variation->combo_variations : [],
                ];

                $line_quantity = $line_data['quantity'];
                if (! empty($line_data['unit'])) {
                    $unit_name = trim($line_data['unit']);
                    // Escopo de business (ADR 0093): o legado aceitava unidade homônima de
                    // outro negócio — e o multiplicador dela entrava na quantidade.
                    $unit = Unit::where('business_id', $business_id)
                                ->where(function ($q) use ($unit_name) {
                                    $q->where('actual_name', $unit_name)
                                        ->orWhere('short_name', $unit_name);
                                })
                                ->first();

                    if (empty($unit)) {
                        throw new \Exception(__('lang_v1.import_sale_unit_not_found', ['row' => $row_index, 'unit_name' => $unit_name]));
                    }

                    //Check if sub unit
                    if ($unit->id != $product->unit_id) {
                        $temp['sub_unit_id'] = $unit->id;
                        $temp['base_unit_multiplier'] = $unit->base_unit_multiplier;
                        $line_quantity = ($line_quantity * $unit->base_unit_multiplier);
                    }
                }
                $order_total += ($temp['unit_price_inc_tax'] * $line_quantity);

                $sell_lines[] = $temp;

                $row_index++;
            }

            $first_sell_line = $data[0];
            $contact = null;
            //get contact
            if (! empty($first_sell_line['customer_phone_number'])) {
                $contact = Contact::where('business_id', $business_id)
                                ->where('mobile', $first_sell_line['customer_phone_number'])
                                ->first();
            } elseif (! empty($first_sell_line['customer_email'])) {
                $contact = Contact::where('business_id', $business_id)
                                ->where('email', $first_sell_line['customer_email'])
                                ->first();
            }
            if ($contact === null) {
                $customer_name = ! empty($first_sell_line['customer_name']) ? $first_sell_line['customer_name'] : $first_sell_line['customer_phone_number'];
                $contact = Contact::create([
                    'business_id' => $business_id,
                    'type' => 'customer',
                    'name' => $customer_name,
                    'email' => $first_sell_line['customer_email'],
                    'mobile' => $first_sell_line['customer_phone_number'],
                    'created_by' => $user_id,
                ]);
            }

            $sale_data = [
                'invoice_no' => $first_sell_line['invoice_no'],
                'location_id' => $location_id,
                'status' => 'final',
                'contact_id' => $contact->id,
                'final_total' => ! empty($first_sell_line['order_total']) ? $first_sell_line['order_total'] : $order_total,
                'transaction_date' => ! empty($first_sell_line['date']) ? $first_sell_line['date'] : $now,
                'discount_amount' => 0,
                'import_batch' => $import_batch,
                'import_time' => $now,
                'commission_agent' => null,
            ];

            if ($is_types_service_enabled && ! empty($first_sell_line['types_of_service'])) {
                $types_of_service = TypesOfService::where('business_id', $business_id)
                                                ->where('name', $first_sell_line['types_of_service'])
                                                ->first();

                if (empty($types_of_service)) {
                    throw new \Exception(__('lang_v1.types_of_servicet_not_found', ['row' => $row_index, 'types_of_service_name' => $first_sell_line['types_of_service']]));
                }

                $sale_data['types_of_service_id'] = $types_of_service->id;
                $sale_data['service_custom_field_1'] = ! empty($first_sell_line['service_custom_field1']) ? $first_sell_line['service_custom_field1'] : null;
                $sale_data['service_custom_field_2'] = ! empty($first_sell_line['service_custom_field2']) ? $first_sell_line['service_custom_field2'] : null;
                $sale_data['service_custom_field_3'] = ! empty($first_sell_line['service_custom_field3']) ? $first_sell_line['service_custom_field3'] : null;
                $sale_data['service_custom_field_4'] = ! empty($first_sell_line['service_custom_field4']) ? $first_sell_line['service_custom_field4'] : null;
            }

            $invoice_total = [
                'total_before_tax' => ! empty($first_sell_line['order_total']) ? $first_sell_line['order_total'] : $order_total,
                'tax' => 0,
            ];

            // Docblocks legados do TransactionUtil não batem com o uso (invoice_total é array,
            // location_id é int) — chamadas idênticas às do controller, que estavam no baseline.
            $transaction = $this->transactionUtil->createSellTransaction($business_id, $sale_data, $invoice_total, $user_id, false); // @phpstan-ignore argument.type

            $this->transactionUtil->createOrUpdateSellLines($transaction, $sell_lines, $location_id, false, null, [], false); // @phpstan-ignore argument.type

            foreach ($sell_lines as $line) {
                if ($line['enable_stock']) {
                    $this->productUtil->decreaseProductQuantity(
                        $line['product_id'],
                        $line['variation_id'],
                        $location_id,
                        $line['quantity']
                    );
                }

                if ($line['type'] == 'combo') {
                    $line_total_quantity = $line['quantity'];
                    if (! empty($line['base_unit_multiplier'])) {
                        $line_total_quantity = $line_total_quantity * $line['base_unit_multiplier'];
                    }

                    //Decrease quantity of combo as well.
                    $combo_details = [];
                    foreach ($line['combo_variations'] as $combo_variation) {
                        $combo_variation_obj = Variation::find($combo_variation['variation_id']);

                        //Multiply both subunit multiplier of child product and parent product to the quantity
                        $combo_variation_quantity = $combo_variation['quantity'];
                        if (! empty($combo_variation['unit_id'])) {
                            $combo_variation_unit = Unit::find($combo_variation['unit_id']);
                            if (! empty($combo_variation_unit->base_unit_multiplier)) {
                                $combo_variation_quantity = $combo_variation_quantity * $combo_variation_unit->base_unit_multiplier;
                            }
                        }

                        $combo_details[] = [
                            'product_id' => $combo_variation_obj->product_id,
                            'variation_id' => $combo_variation['variation_id'],
                            'quantity' => $combo_variation_quantity * $line_total_quantity,
                        ];
                    }

                    $this->productUtil
                        ->decreaseProductQuantityCombo(
                            $combo_details,
                            $location_id
                        );
                }
            }

            //Update payment status
            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

            $business_details = $this->businessUtil->getDetails($business_id);
            $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

            $business = ['id' => $business_id,
                // O legado lia da sessão (`business.accounting_method`); é a mesma coluna.
                'accounting_method' => $business_model->accounting_method,
                'location_id' => $location_id,
                'pos_settings' => $pos_settings,
            ];
            $this->transactionUtil->mapPurchaseSell($business, $transaction->sell_lines, 'purchase');

            $feitas++;
            if ($aoProgredir !== null) {
                $aoProgredir($feitas, $total_vendas);
            }
        }

        return ['lote' => (int) $import_batch, 'vendas' => $feitas];
    }

    /** Linhas de dados (sem o cabeçalho) de uma planilha já lida. */
    public function contarLinhas(array $parsed_array): int
    {
        return max(0, count($parsed_array) - 1);
    }
}
