<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Services;

use App\Business;
use App\BusinessLocation;
use App\Utils\ContactUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use Illuminate\Support\Facades\DB;
use Modules\Marketplaces\Entities\Anuncio;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Throwable;

/**
 * Pedido PAGO do Mercado Livre → venda no oimpresso (US-MKT-007 · ADR 0439).
 *
 * Regras de [W] (2026-10-26): o estoque baixa quando o ML confirma pago; a venda entra no local
 * escolhido na conta. Mesmo caminho do núcleo que o pedido da WooCommerce usa:
 * createSellTransaction → createOrUpdateSellLines → decreaseProductQuantity → mapPurchaseSell.
 *
 * REGRA MESTRE de valor/estoque — o que este serviço garante:
 *  - preço de cada linha = `unit_price` do item no ML, arredondado a 2 casas (sem float ambíguo);
 *  - o total das linhas tem de BATER com o `total_amount` do ML (diferença > 1 centavo = recusa,
 *    a venda não é criada). São os dois caminhos independentes do mesmo número;
 *  - sem pagamento lançado: o Mercado Pago só libera o dinheiro dias depois (D6 da ADR 0439), então a
 *    venda nasce "a receber" e a baixa entra na conciliação (US-MKT-015);
 *  - frete e taxa do ML não entram na venda (ficam no pedido do marketplace).
 *
 * Cliente: consumidor final da empresa (o ML esconde os dados do comprador). Tudo numa transação de
 * banco com o pedido travado: notificação repetida nunca cria duas vendas.
 *
 * NUVEMSHOP (ADR 0443, fatia c) — o mesmo serviço, com o pedido traduzido por canal em `traduzir()`.
 * Na loja própria o cliente paga frete e recebe desconto da própria empresa, então os dois entram
 * na venda (`shipping_charges` e `discount_amount`), e `final_total` = linhas − desconto + frete. Dupla prova:
 *  (a) soma das linhas (preço × quantidade) = `subtotal` da loja;
 *  (b) subtotal − desconto + frete = `total` da loja, com frete = `shipping_cost_customer`.
 * Qualquer diferença > 1 centavo recusa. No Mercado Livre desconto e frete são 0 e o esperado das
 * linhas é o próprio `total_amount`: comportamento idêntico ao de antes.
 */
class PedidoParaVenda
{
    public const OK = 'ok';

    public function __construct(
        private TransactionUtil $transactionUtil,
        private ProductUtil $productUtil,
        private ContactUtil $contactUtil,
        private IniciarPipelineMarketplace $pipeline,
    ) {
    }

    /**
     * Itens do pedido mapeados para variações da empresa, ou o motivo de não conseguir.
     *
     * @param  array<string, mixed>  $raw
     * @return array{linhas: array<int, array<string, mixed>>, total: float}|string
     */
    public function linhas(int $businessId, int $marketplaceId, array $raw, string $canal = Marketplace::MERCADO_LIVRE): array|string
    {
        $linhas = [];
        $total = 0.0;
        foreach ($this->itens($raw, $canal) as [$itemId, $sku, $qtd, $preco]) {
            if ($qtd <= 0) {
                return 'item com quantidade inválida';
            }

            $variacao = $this->variacao($businessId, $marketplaceId, $itemId, $sku);
            if ($variacao === null) {
                return "item {$itemId} sem anúncio vinculado nem SKU conhecido";
            }

            $linhas[] = [
                'product_id' => (int) $variacao->product_id,
                'variation_id' => (int) $variacao->id,
                'quantity' => $qtd,
                'unit_price' => $preco,
                'unit_price_inc_tax' => $preco,
                'item_tax' => 0,
                'tax_id' => null,
                'enable_stock' => (int) $variacao->product->enable_stock,
            ];
            $total += round($preco * $qtd, 2);
        }
        if ($linhas === []) {
            return 'pedido sem itens';
        }

        return ['linhas' => $linhas, 'total' => round($total, 2)];
    }

    /**
     * Itens do pedido no formato do canal → [id externo do item, sku, quantidade, preço unitário 2 casas].
     * Nuvemshop: o vínculo do anúncio é pela VARIANTE (`variant_id`); `price` é o preço unitário.
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, array{0: string, 1: string, 2: float, 3: float}>
     */
    private function itens(array $raw, string $canal): array
    {
        if ($canal === Marketplace::NUVEMSHOP) {
            return array_map(fn ($p) => [
                (string) ($p['variant_id'] ?? ''), (string) ($p['sku'] ?? ''), (float) ($p['quantity'] ?? 0), round((float) ($p['price'] ?? 0), 2),
            ], (array) ($raw['products'] ?? []));
        }

        return array_map(fn ($i) => [
            (string) ($i['item']['id'] ?? ''), (string) ($i['item']['seller_sku'] ?? ''), (float) ($i['quantity'] ?? 0), round((float) ($i['unit_price'] ?? 0), 2),
        ], (array) ($raw['order_items'] ?? []));
    }

    /**
     * Valores do pedido por canal: o que as linhas têm de somar, o desconto, o frete e o total final.
     * Devolve o motivo (string) quando a Nuvemshop não fecha subtotal − desconto + frete = total.
     *
     * @return array{esperado_linhas: float, desconto: float, frete: float, final: float}|string
     */
    private function valores(PedidoMarketplace $pedido, string $canal): array|string
    {
        $total = round((float) $pedido->total_amount, 2);
        if ($canal !== Marketplace::NUVEMSHOP) {
            return ['esperado_linhas' => $total, 'desconto' => 0.0, 'frete' => 0.0, 'final' => $total];
        }

        $raw = (array) $pedido->raw_payload;
        $subtotal = round((float) ($raw['subtotal'] ?? 0), 2);
        $desconto = round((float) ($raw['discount'] ?? 0), 2);
        $frete = round((float) ($raw['shipping_cost_customer'] ?? 0), 2);
        if ($desconto < 0 || $frete < 0 || abs(round($subtotal - $desconto + $frete, 2) - $total) > 0.01) {
            return "subtotal ({$subtotal}) − desconto ({$desconto}) + frete ({$frete}) não bate com o total da Nuvemshop ({$total})";
        }

        return ['esperado_linhas' => $subtotal, 'desconto' => $desconto, 'frete' => $frete, 'final' => $total];
    }

    /** Converte o pedido. Devolve self::OK ou o motivo de não ter criado a venda. */
    public function converter(PedidoMarketplace $pedido): string
    {
        $businessId = (int) $pedido->business_id;

        try {
            return DB::transaction(function () use ($pedido, $businessId) {
                // SUPERADMIN: Trava a linha: duas notificações do mesmo pedido em paralelo não criam duas vendas.
                $travado = PedidoMarketplace::withoutGlobalScopes()->where('business_id', $businessId)
                    ->where('id', $pedido->id)->lockForUpdate()->first();
                if ($travado === null || $travado->transaction_id !== null) {
                    return 'pedido já virou venda';
                }
                $canal = Marketplace::query()->whereKey($travado->marketplace_id)->first(['slug', 'nome']);
                $slug = (string) ($canal->slug ?? Marketplace::MERCADO_LIVRE);
                $nome = (string) ($canal->nome ?? 'Mercado Livre');
                $feminino = $slug === Marketplace::NUVEMSHOP;
                if ($travado->status_external !== 'paid') {
                    return 'pedido ainda não está pago '.($feminino ? 'na ' : 'no ').$nome;
                }

                // SUPERADMIN: roda dentro de job sem sessão; business_id explícito na consulta.
                $conta = ContaVendedor::withoutGlobalScopes()->where('business_id', $businessId)->where('id', $travado->account_id)->first();
                $local = $conta?->location_id
                    ? BusinessLocation::query()->where('business_id', $businessId)->where('id', $conta->location_id)->first()
                    : null;
                if ($local === null) {
                    return 'conta sem local de venda escolhido';
                }

                $mapa = $this->linhas($businessId, (int) $travado->marketplace_id, (array) $travado->raw_payload, $slug);
                if (is_string($mapa)) {
                    return $mapa;
                }
                $valores = $this->valores($travado, $slug);
                if (is_string($valores)) {
                    return $valores;
                }
                if (abs($mapa['total'] - $valores['esperado_linhas']) > 0.01) {
                    return "total das linhas ({$mapa['total']}) não bate com o ".($feminino ? 'subtotal da ' : 'total do ')."{$nome} ({$valores['esperado_linhas']})";
                }

                $business = Business::query()->findOrFail($businessId);
                $userId = (int) $business->owner_id;
                $cliente = $this->contactUtil->getWalkInCustomer($businessId, false);
                if ($cliente === null) {
                    return 'empresa sem cliente consumidor final';
                }

                $entrada = [
                    'location_id' => (int) $local->id,
                    'status' => 'final',
                    'contact_id' => (int) $cliente->id,
                    'transaction_date' => ($travado->date_created_external ?? now())->format('Y-m-d H:i:s'),
                    // Total da venda sai das LINHAS gravadas (− desconto + frete), como sempre foi no ML: a
                    // venda fica coerente com o que ela mesma contém. O total do canal só confere (±1 centavo).
                    'final_total' => round($mapa['total'] - $valores['desconto'] + $valores['frete'], 2),
                    'discount_type' => 'fixed',
                    'discount_amount' => $valores['desconto'],
                    'shipping_charges' => $valores['frete'],
                    'source' => $slug,
                    'staff_note' => 'Pedido '.$nome.' '.$travado->order_id_external,
                    'is_direct_sale' => 1,
                ];

                $venda = $this->transactionUtil->createSellTransaction(
                    $businessId, $entrada, ['total_before_tax' => $mapa['total'], 'tax' => 0], $userId, false
                );
                $this->transactionUtil->createOrUpdateSellLines($venda, $mapa['linhas'], (int) $local->id, false, null, [], false);
                // Sem pagamento lançado: o status sai do próprio núcleo (vira 'due').
                $this->transactionUtil->updatePaymentStatus($venda->id, $venda->final_total);

                foreach ($mapa['linhas'] as $l) {
                    if ($l['enable_stock']) {
                        $this->productUtil->decreaseProductQuantity($l['product_id'], $l['variation_id'], (int) $local->id, $l['quantity']);
                    }
                }
                $this->transactionUtil->mapPurchaseSell([
                    'id' => $businessId,
                    'accounting_method' => $business->accounting_method,
                    'location_id' => (int) $local->id,
                    'pos_settings' => $business->pos_settings,
                ], $venda->sell_lines, 'purchase');

                // Estágio inicial do processo `venda_marketplace` (ADR 0143), na mesma transação.
                $this->pipeline->iniciar($venda->fresh(), $userId, $slug.':'.$travado->order_id_external);

                $travado->forceFill(['transaction_id' => (int) $venda->id])->save();

                return self::OK;
            });
        } catch (Throwable $e) {
            return 'falha ao criar a venda: '.class_basename($e);
        }
    }

    private function variacao(int $businessId, int $marketplaceId, string $itemId, string $sku): ?Variation
    {
        if ($itemId !== '') {
            // SUPERADMIN: roda dentro de job sem sessão; business_id explícito na consulta.
            $anuncio = Anuncio::withoutGlobalScopes()->where('business_id', $businessId)
                ->where('marketplace_id', $marketplaceId)->where('item_id_external', $itemId)->first();
            if ($anuncio !== null) {
                $id = $anuncio->variation_id ?: Variation::query()->where('product_id', $anuncio->product_id)->value('id');
                if ($id) {
                    return Variation::query()->with('product')->whereHas('product', fn ($q) => $q->where('business_id', $businessId))->find($id);
                }
            }
        }
        if ($sku !== '') {
            return Variation::query()->with('product')->where('sub_sku', $sku)
                ->whereHas('product', fn ($q) => $q->where('business_id', $businessId))->first();
        }

        return null;
    }
}
