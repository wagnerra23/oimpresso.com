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
 */
class PedidoParaVenda
{
    public const OK = 'ok';

    public function __construct(
        private TransactionUtil $transactionUtil,
        private ProductUtil $productUtil,
        private ContactUtil $contactUtil,
    ) {
    }

    /**
     * Itens do pedido mapeados para variações da empresa, ou o motivo de não conseguir.
     *
     * @param  array<string, mixed>  $raw
     * @return array{linhas: array<int, array<string, mixed>>, total: float}|string
     */
    public function linhas(int $businessId, int $marketplaceId, array $raw): array|string
    {
        $linhas = [];
        $total = 0.0;
        foreach ((array) ($raw['order_items'] ?? []) as $item) {
            $itemId = (string) ($item['item']['id'] ?? '');
            $sku = (string) ($item['item']['seller_sku'] ?? '');
            $qtd = (float) ($item['quantity'] ?? 0);
            $preco = round((float) ($item['unit_price'] ?? 0), 2);
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

    /** Converte o pedido. Devolve self::OK ou o motivo de não ter criado a venda. */
    public function converter(PedidoMarketplace $pedido): string
    {
        $businessId = (int) $pedido->business_id;

        try {
            return DB::transaction(function () use ($pedido, $businessId) {
                // Trava a linha: duas notificações do mesmo pedido em paralelo não criam duas vendas.
                $travado = PedidoMarketplace::withoutGlobalScopes()->where('business_id', $businessId)
                    ->where('id', $pedido->id)->lockForUpdate()->first();
                if ($travado === null || $travado->transaction_id !== null) {
                    return 'pedido já virou venda';
                }
                if ($travado->status_external !== 'paid') {
                    return 'pedido ainda não está pago no Mercado Livre';
                }

                $conta = ContaVendedor::withoutGlobalScopes()->where('business_id', $businessId)->where('id', $travado->account_id)->first();
                $local = $conta?->location_id
                    ? BusinessLocation::query()->where('business_id', $businessId)->where('id', $conta->location_id)->first()
                    : null;
                if ($local === null) {
                    return 'conta sem local de venda escolhido';
                }

                $mapa = $this->linhas($businessId, (int) $travado->marketplace_id, (array) $travado->raw_payload);
                if (is_string($mapa)) {
                    return $mapa;
                }
                $totalMl = round((float) $travado->total_amount, 2);
                if (abs($mapa['total'] - $totalMl) > 0.01) {
                    return "total das linhas ({$mapa['total']}) não bate com o total do Mercado Livre ({$totalMl})";
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
                    'final_total' => $mapa['total'],
                    'discount_type' => 'fixed',
                    'discount_amount' => 0,
                    'shipping_charges' => 0,
                    'source' => 'mercado_livre',
                    'staff_note' => 'Pedido Mercado Livre '.$travado->order_id_external,
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
