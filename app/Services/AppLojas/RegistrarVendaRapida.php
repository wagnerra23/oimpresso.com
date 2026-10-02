<?php

declare(strict_types=1);

namespace App\Services\AppLojas;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Exceptions\PurchaseSellMismatch;
use App\Http\Controllers\Api\App\VendaRapidaController;
use App\Transaction;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\NotificationUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/**
 * Grava a venda rápida do app das lojas (tela 11). Contrato: API-CONTRATO-v1 §2.2. REGRA MESTRE
 * (valor + estoque): o merge depende da dupla prova + tabela antes→depois + ok do [W].
 *
 * É o caminho da venda DIRETA da web (SellPosController::store com is_direct_sale=1), na mesma
 * ordem: createSellTransaction → createOrUpdateSellLines → createOrUpdatePaymentLines → baixa de
 * estoque → updatePaymentStatus → mapPurchaseSell. A venda nasce `final` e FORA da FSM.
 *
 * NÚMEROS: nada do app passa pelo Util::num_uf (medido: num_uf("1.500") = 1500). Os valores são
 * convertidos aqui em centavos INTEIROS a partir do texto e os utilitários recebem `uf_data=false`,
 * como o conector de API (Modules/Connector SellController::store). Na v1 a quantidade é inteira: o
 * total da venda (soma de preço × quantidade) fica exato em centavos, sem arredondamento por linha.
 *
 * DUPLA PROVA no próprio fluxo: o preço de cada item é recalculado pela MESMA função do GET
 * (VendaRapidaController::precoDeVenda) e o total pela soma em centavos; se o app mostrou outro
 * número, a venda é recusada antes de qualquer escrita.
 *
 * Deve ser chamado DENTRO de uma transação de banco (o chamador desfaz tudo em qualquer exceção).
 * Tier 0 (ADR 0093): tudo filtrado pelo business do usuário.
 */
final class RegistrarVendaRapida
{
    /** forma de pagamento do app → a do ERP. PIX = custom_pay_1, a convenção da web (SellController/QuickPayment*). */
    public const METODOS = [
        'dinheiro' => ['method' => 'cash', 'card_type' => null, 'rotulo' => 'Dinheiro'],
        'credito' => ['method' => 'card', 'card_type' => 'credit', 'rotulo' => 'Crédito'],
        'debito' => ['method' => 'card', 'card_type' => 'debit', 'rotulo' => 'Débito'],
        'pix' => ['method' => 'custom_pay_1', 'card_type' => null, 'rotulo' => 'PIX'],
    ];

    public function __construct(
        private readonly TransactionUtil $transactionUtil,
        private readonly ProductUtil $productUtil,
        private readonly ContactUtil $contactUtil,
        private readonly BusinessUtil $businessUtil,
        private readonly NotificationUtil $notificationUtil,
    ) {
    }

    /**
     * @param  array{cliente_id:?int, metodo:string, itens:list<array{variacao_id:int|string, quantidade:string, preco_unitario:string}>, total_previsto:string}  $d
     * @return array{transaction: Transaction, resposta: array<string,mixed>}
     */
    public function registrar(User $user, BusinessLocation $local, array $d): array
    {
        $bizId = (int) $user->business_id;
        $business = Business::findOrFail($bizId);
        $contato = $this->cliente($bizId, $d['cliente_id'] ?? null);
        [$produtos, $resumo, $totalC] = $this->linhas($bizId, $local, $d['itens']);

        if ($totalC !== self::centavos($d['total_previsto'])) {
            throw new VendaRapidaInvalida(['total_previsto' => 'O total mudou para ' . self::brl($totalC) . '. Revise o carrinho.']);
        }

        $total = $totalC / 100;
        // getCustomerGroup devolve [] (sem grupo) ou o objeto do grupo; o docblock diz só array.
        $cg = $this->contactUtil->getCustomerGroup($bizId, $contato->id);
        $grupoClienteId = is_object($cg) && ! empty($cg->id) ? (int) $cg->id : null;
        $input = [
            'location_id' => $local->id,
            'status' => 'final',
            'contact_id' => $contato->id,
            'transaction_date' => now(),
            'final_total' => $total,
            'discount_type' => null,
            'discount_amount' => 0,
            'tax_rate_id' => null,
            'is_direct_sale' => 1,
            'is_created_from_api' => 1,
            'customer_group_id' => $grupoClienteId,
            'selling_price_group_id' => $local->selling_price_group_id ?: null,
            'commission_agent' => $business->sales_cmsn_agnt === 'logged_in_user' ? $user->id : null,
        ];
        $invoiceTotal = $this->productUtil->calculateInvoiceTotal($produtos, 0, null, false); // 0 = sem imposto da venda (o método testa !empty)

        $tx = $this->transactionUtil->createSellTransaction($bizId, $input, $invoiceTotal, $user->id, false);
        $this->transactionUtil->createOrUpdateSellLines($tx, $produtos, $local->id, false, null, [], false); // @phpstan-ignore argument.type (docblock do TransactionUtil diz array; o id do local é o que o SellPosController passa)

        $metodo = self::METODOS[$d['metodo']];
        $this->transactionUtil->createOrUpdatePaymentLines($tx, [[
            'amount' => $total,
            'method' => $metodo['method'],
            'card_type' => $metodo['card_type'],
            'paid_on' => now()->toDateTimeString(),
            // createOrUpdatePaymentLines lê transaction_no_<N> SEM isset para custom_pay_N.
            'transaction_no_1' => null,
        ]], $bizId, $user->id, false);

        foreach ($produtos as $p) {
            if ((int) $p['enable_stock'] === 1) {
                $this->productUtil->decreaseProductQuantity($p['product_id'], $p['variation_id'], $local->id, $p['quantity']);
            }
        }

        $tx->payment_status = $this->transactionUtil->updatePaymentStatus($tx->id, $tx->final_total);

        $pos = empty($business->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business->pos_settings, true);
        try {
            $this->transactionUtil->mapPurchaseSell([
                'id' => $bizId,
                'accounting_method' => $business->accounting_method,
                'location_id' => $local->id,
                'pos_settings' => $pos,
            ], $tx->sell_lines, 'purchase');
        } catch (PurchaseSellMismatch $e) {
            $campos = [];
            foreach ($resumo as $i => $r) {
                if ($e->variationId === null || $r['variacao_id'] === $e->variationId) {
                    $campos["itens.{$i}.quantidade"] = 'Estoque insuficiente (disponível ' . self::qtd($r['disponivel']) . ').';
                }
            }
            throw new VendaRapidaInvalida($campos ?: ['itens' => 'Estoque insuficiente.']);
        }

        $this->transactionUtil->activityLog($tx, 'added', null, ['from_api' => 'app-lojas']);
        $this->notificationUtil->autoSendNotification($bizId, 'new_sale', $tx, $tx->contact);

        return ['transaction' => $tx, 'resposta' => [
            'id' => (int) $tx->id,
            'numero' => (string) $tx->invoice_no,
            'data' => $tx->transaction_date instanceof \DateTimeInterface
                ? $tx->transaction_date->format('Y-m-d\TH:i:sP')
                : \Carbon\Carbon::parse((string) $tx->transaction_date)->format('Y-m-d\TH:i:sP'),
            'total' => $total,
            'itens' => array_map(fn ($r) => [
                'variacao_id' => $r['variacao_id'],
                'nome' => $r['nome'],
                'quantidade' => $r['quantidade'],
                'preco_unitario' => $r['precoC'] / 100,
                'subtotal' => $r['subtotalC'] / 100,
            ], $resumo),
            'metodo' => $metodo['rotulo'],
        ]];
    }

    /** cliente_id null = consumidor final do business (contacts.is_default=1, ContactUtil::getWalkInCustomer). */
    private function cliente(int $bizId, mixed $clienteId): Contact
    {
        $q = Contact::where('business_id', $bizId)->whereIn('type', ['customer', 'both']);
        $c = $clienteId === null ? $q->where('is_default', 1)->first() : $q->where('id', (int) $clienteId)->first();
        if ($c === null) {
            throw new VendaRapidaInvalida(['cliente_id' => $clienteId === null
                ? 'A empresa não tem consumidor final cadastrado. Escolha um cliente.'
                : 'Cliente não encontrado.']);
        }

        return $c;
    }

    /**
     * Linhas no formato do createOrUpdateSellLines (uf_data=false) + resumo da resposta + total em centavos.
     *
     * @return array{0: list<array<string,mixed>>, 1: list<array<string,mixed>>, 2: int}
     */
    private function linhas(int $bizId, BusinessLocation $local, array $itens): array
    {
        $ids = array_values(array_unique(array_map(fn ($i) => (int) $i['variacao_id'], $itens)));
        $vars = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('product_locations as pl', fn ($j) => $j->on('pl.product_id', '=', 'p.id')->where('pl.location_id', '=', $local->id))
            ->leftJoin('variation_location_details as vld', fn ($j) => $j->on('vld.variation_id', '=', 'v.id')->where('vld.location_id', '=', $local->id))
            ->leftJoin('tax_rates as tr', fn ($j) => $j->on('tr.id', '=', 'p.tax')->where('tr.business_id', '=', $bizId))
            ->where('p.business_id', $bizId)
            ->where('p.is_inactive', 0)
            ->where('p.not_for_selling', 0)
            ->whereIn('p.type', ['single', 'variable'])
            ->whereNull('v.deleted_at')
            ->whereIn('v.id', $ids)
            ->get(['v.id', 'v.name as variacao', 'p.id as product_id', 'p.name as produto', 'p.type', 'p.enable_stock',
                'p.tax as tax_id', 'tr.amount as tax_amount', 'v.sell_price_inc_tax', 'vld.qty_available'])
            ->keyBy('id');

        $produtos = [];
        $resumo = [];
        $erros = [];
        $totalC = 0;
        foreach ($itens as $i => $item) {
            $v = $vars->get((int) $item['variacao_id']);
            if ($v === null) {
                $erros["itens.{$i}.variacao_id"] = 'Produto não está disponível para venda neste local.';
                continue;
            }
            $qtd = self::quantidadeInteira($item['quantidade']);
            if ($qtd === null) {
                $erros["itens.{$i}.quantidade"] = 'Informe uma quantidade inteira maior que zero.';
                continue;
            }
            $precoC = self::centavos((string) VendaRapidaController::precoDeVenda($local, (int) $v->id, (float) $v->sell_price_inc_tax, $v->tax_id));
            if ($precoC !== self::centavos($item['preco_unitario'])) {
                $erros["itens.{$i}.preco_unitario"] = 'O preço mudou para ' . self::brl($precoC) . '.';
                continue;
            }

            $subtotalC = $precoC * $qtd;
            $totalC += $subtotalC;
            $inc = $precoC / 100;
            $taxa = $v->tax_amount !== null ? (float) $v->tax_amount : null;
            // Mesma conta do Util::calc_percentage_base (number * 100 / (100 + percent)), sem o int do docblock.
            $exc = $taxa !== null ? ($inc * 100) / (100 + $taxa) : $inc;

            $produtos[] = [
                'product_id' => (int) $v->product_id,
                'variation_id' => (int) $v->id,
                'quantity' => $qtd,
                'unit_price' => $exc,
                'unit_price_inc_tax' => $inc,
                'item_tax' => $inc - $exc,
                'tax_id' => $taxa !== null ? (int) $v->tax_id : null,
                'line_discount_type' => null,
                'line_discount_amount' => 0,
                'enable_stock' => (int) $v->enable_stock,
                'product_type' => $v->type,
            ];
            $resumo[$i] = [
                'variacao_id' => (int) $v->id,
                'nome' => $v->type === 'variable' && $v->variacao !== 'DUMMY' ? $v->produto . ' — ' . $v->variacao : $v->produto,
                'quantidade' => $qtd,
                'precoC' => $precoC,
                'subtotalC' => $subtotalC,
                'disponivel' => (float) ($v->qty_available ?? 0),
            ];
        }
        if ($erros !== []) {
            throw new VendaRapidaInvalida($erros);
        }

        return [$produtos, $resumo, $totalC];
    }

    /** "12.50" / "12.5" / "12" → 1250 centavos, sem float. O formato já foi validado (até 2 casas). */
    public static function centavos(string $texto): int
    {
        [$int, $dec] = array_pad(explode('.', $texto, 2), 2, '');

        return ((int) $int) * 100 + (int) str_pad($dec, 2, '0');
    }

    /** "3" / "3.0" / "3.00" → 3. Fração diferente de zero ou zero → null (v1 vende só inteiro). */
    private static function quantidadeInteira(string $texto): ?int
    {
        $c = self::centavos($texto);

        return $c > 0 && $c % 100 === 0 ? intdiv($c, 100) : null;
    }

    private static function brl(int $centavos): string
    {
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }

    private static function qtd(float $q): string
    {
        return rtrim(rtrim(number_format($q, 4, ',', ''), '0'), ',');
    }
}
