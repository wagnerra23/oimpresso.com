<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\Utils\Util;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cotação de compra entre fornecedores — US-COM-031 (decisão coordenação 2026-10-26).
 *
 * O comprador abre a cotação com os itens (avulsos, de uma requisição de compra, ou de outro módulo
 * pelo `origem`/`origem_id` — a Oficina consome esta mesma cotação), convida fornecedores, digita o
 * preço e o prazo que cada um respondeu, compara e fecha: `fechar()` gera o pedido de compra de
 * cada fornecedor vencedor (mexe em valor, regra mestre — ver o docblock dele).
 *
 * Comparação por item: vence o menor preço unitário; empate, o menor prazo; ainda empatado, quem
 * respondeu primeiro. Item sem resposta fica sem vencedor.
 *
 * Tier 0 (ADR 0093): `$businessId` é argumento; local, variação, fornecedor e requisição são
 * conferidos contra a empresa antes de gravar. Preços e quantidades em string DECIMAL, sem float.
 */
final class CotacaoDeCompra
{
    public const ABERTA = 'aberta';

    /**
     * @param  list<array{variation_id:int, quantidade:string|int|float}>  $itens
     * @param  list<int>  $fornecedores  contacts.id
     */
    public function criar(int $businessId, int $locationId, array $itens, array $fornecedores, int $userId, ?string $origem = null, ?int $origemId = null): int
    {
        if ($itens === [] || $fornecedores === []) {
            throw new InvalidArgumentException('A cotação precisa de ao menos um item e um fornecedor.');
        }
        if (! DB::table('business_locations')->where('id', $locationId)->where('business_id', $businessId)->exists()) {
            throw new InvalidArgumentException('Local inválido para este negócio.');
        }

        $variacoes = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)->whereIn('v.id', array_column($itens, 'variation_id'))
            ->pluck('v.product_id', 'v.id');
        foreach ($itens as $i) {
            if (! isset($variacoes[$i['variation_id']]) || (float) $i['quantidade'] <= 0) {
                throw new InvalidArgumentException('Item inválido para este negócio.');
            }
        }

        $fornecedores = array_values(array_unique(array_map('intval', $fornecedores)));
        $validos = DB::table('contacts')->where('business_id', $businessId)->whereIn('id', $fornecedores)
            ->whereIn('type', ['supplier', 'both'])->count();
        if ($validos !== count($fornecedores)) {
            throw new InvalidArgumentException('Fornecedor inválido para este negócio.');
        }

        return DB::transaction(function () use ($businessId, $locationId, $itens, $fornecedores, $userId, $origem, $origemId, $variacoes) {
            $util = app(Util::class);
            $n = $util->setAndGetReferenceCount('compras_cotacao', $businessId);
            $agora = now();
            $id = DB::table('compras_cotacoes')->insertGetId([
                'business_id' => $businessId, 'location_id' => $locationId,
                'ref_no' => 'COT-'.$agora->format('Y').'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'status' => self::ABERTA, 'origem' => $origem, 'origem_id' => $origemId,
                'created_by' => $userId, 'created_at' => $agora, 'updated_at' => $agora,
            ]);
            DB::table('compras_cotacao_itens')->insert(array_map(fn ($i) => [
                'business_id' => $businessId, 'cotacao_id' => $id,
                'product_id' => (int) $variacoes[$i['variation_id']], 'variation_id' => (int) $i['variation_id'],
                'quantidade' => (string) $i['quantidade'], 'created_at' => $agora, 'updated_at' => $agora,
            ], $itens));
            DB::table('compras_cotacao_fornecedores')->insert(array_map(fn ($c) => [
                'business_id' => $businessId, 'cotacao_id' => $id, 'contact_id' => $c,
                'created_at' => $agora, 'updated_at' => $agora,
            ], $fornecedores));

            return $id;
        });
    }

    /** Cotação com os itens de uma requisição de compra da empresa (origem = requisicao). */
    public function daRequisicao(int $businessId, int $requisicaoId, array $fornecedores, int $userId): int
    {
        $req = DB::table('transactions')->where('business_id', $businessId)->where('type', 'purchase_requisition')
            ->where('id', $requisicaoId)->first(['id', 'location_id']);
        if ($req === null) {
            throw new InvalidArgumentException('Requisição inválida para este negócio.');
        }
        $itens = DB::table('purchase_lines')->where('transaction_id', $req->id)
            ->get(['variation_id', 'quantity'])
            ->map(fn ($l) => ['variation_id' => (int) $l->variation_id, 'quantidade' => (string) $l->quantity])->all();

        return $this->criar($businessId, (int) $req->location_id, $itens, $fornecedores, $userId, 'requisicao', (int) $req->id);
    }

    /**
     * Preço e prazo que um fornecedor respondeu, por item. Repetir substitui a resposta anterior.
     *
     * @param  array<int, array{preco_unitario:string|int|float, prazo_dias?:int|null}>  $porItem  item_id → resposta
     */
    public function responder(int $businessId, int $cotacaoId, int $contactId, array $porItem): void
    {
        $cotacao = $this->aberta($businessId, $cotacaoId);
        if (! DB::table('compras_cotacao_fornecedores')->where('cotacao_id', $cotacao->id)->where('contact_id', $contactId)->exists()) {
            throw new InvalidArgumentException('Este fornecedor não foi convidado para a cotação.');
        }
        $itensDaCotacao = DB::table('compras_cotacao_itens')->where('cotacao_id', $cotacao->id)->pluck('id')->all();
        $agora = now();
        foreach ($porItem as $itemId => $r) {
            if (! in_array((int) $itemId, array_map('intval', $itensDaCotacao), true) || (float) $r['preco_unitario'] < 0) {
                throw new InvalidArgumentException('Resposta para item que não é desta cotação.');
            }
            DB::table('compras_cotacao_respostas')->updateOrInsert(
                ['item_id' => (int) $itemId, 'contact_id' => $contactId],
                ['business_id' => $businessId, 'cotacao_id' => $cotacao->id, 'preco_unitario' => (string) $r['preco_unitario'],
                    'prazo_dias' => $r['prazo_dias'] ?? null, 'updated_at' => $agora, 'created_at' => $agora],
            );
        }
    }

    /**
     * Respostas lado a lado e o melhor fornecedor de cada item.
     *
     * @return list<array{item_id:int, variation_id:int, quantidade:string, respostas:list<array{contact_id:int, preco_unitario:string, prazo_dias:?int}>, melhor_contact_id:?int}>
     */
    public function comparar(int $businessId, int $cotacaoId): array
    {
        $cotacao = DB::table('compras_cotacoes')->where('business_id', $businessId)->where('id', $cotacaoId)->first(['id']);
        if ($cotacao === null) {
            throw new InvalidArgumentException('Cotação inválida para este negócio.');
        }
        $respostas = DB::table('compras_cotacao_respostas')->where('cotacao_id', $cotacao->id)->where('business_id', $businessId)
            ->orderBy('id')->get()->groupBy('item_id');

        return DB::table('compras_cotacao_itens')->where('cotacao_id', $cotacao->id)->orderBy('id')->get()
            ->map(function ($i) use ($respostas) {
                $lista = ($respostas[$i->id] ?? collect())->map(fn ($r) => [
                    'contact_id' => (int) $r->contact_id, 'preco_unitario' => (string) $r->preco_unitario,
                    'prazo_dias' => $r->prazo_dias !== null ? (int) $r->prazo_dias : null, 'ordem' => (int) $r->id,
                ])->all();
                usort($lista, fn ($a, $b) => [self::dm($a['preco_unitario']), $a['prazo_dias'] ?? PHP_INT_MAX, $a['ordem']]
                    <=> [self::dm($b['preco_unitario']), $b['prazo_dias'] ?? PHP_INT_MAX, $b['ordem']]);

                return [
                    'item_id' => (int) $i->id, 'variation_id' => (int) $i->variation_id, 'quantidade' => (string) $i->quantidade,
                    'respostas' => array_map(fn ($r) => array_diff_key($r, ['ordem' => 1]), $lista),
                    'melhor_contact_id' => $lista[0]['contact_id'] ?? null,
                ];
            })->values()->all();
    }

    /**
     * Fecha a cotação e gera UM pedido de compra (`transactions.type = purchase_order`, status
     * `ordered`) por fornecedor vencedor, com as linhas que ele ganhou. Mexe em VALOR (regra mestre):
     *   linha  = quantidade × preço respondido (sem imposto nem desconto — a cotação não os tem)
     *   pedido = soma das linhas, em `total_before_tax` e `final_total`
     * `delivery_date` = hoje + o maior prazo respondido pelo fornecedor nas linhas dele.
     * Sem escolha, vale o melhor de `comparar()`; item sem resposta não entra em pedido nenhum.
     * O pedido não move estoque nem gera conta a pagar (só a compra recebida faz isso).
     *
     * @param  array<int,int>  $escolhas  item_id → contact_id, para trocar o vencedor de um item
     * @return list<int> ids dos pedidos criados
     */
    public function fechar(int $businessId, int $cotacaoId, int $userId, array $escolhas = []): array
    {
        return DB::transaction(function () use ($businessId, $cotacaoId, $userId, $escolhas) {
            $cotacao = DB::table('compras_cotacoes')->where('business_id', $businessId)->where('id', $cotacaoId)
                ->lockForUpdate()->first(['id', 'ref_no', 'location_id', 'status']);
            if ($cotacao === null) {
                throw new InvalidArgumentException('Cotação inválida para este negócio.');
            }
            if ($cotacao->status !== self::ABERTA) {
                throw new InvalidArgumentException('A cotação não está aberta.');
            }

            $porFornecedor = [];
            foreach ($this->comparar($businessId, (int) $cotacao->id) as $item) {
                $vencedor = $escolhas[$item['item_id']] ?? $item['melhor_contact_id'];
                if ($vencedor === null) {
                    continue;
                }
                $resposta = collect($item['respostas'])->firstWhere('contact_id', (int) $vencedor);
                if ($resposta === null) {
                    throw new InvalidArgumentException('O fornecedor escolhido não respondeu este item.');
                }
                $porFornecedor[(int) $vencedor][] = $item + ['resposta' => $resposta];
            }

            $util = app(Util::class);
            $agora = now();
            $pedidos = [];
            foreach ($porFornecedor as $contactId => $itens) {
                $total = 0; // dez-milésimos² → convertido no fim
                $linhas = [];
                $prazo = 0;
                foreach ($itens as $i) {
                    $total += self::dm($i['quantidade']) * self::dm($i['resposta']['preco_unitario']);
                    $prazo = max($prazo, (int) ($i['resposta']['prazo_dias'] ?? 0));
                    $linhas[] = [
                        'product_id' => (int) DB::table('compras_cotacao_itens')->where('id', $i['item_id'])->value('product_id'),
                        'variation_id' => $i['variation_id'], 'quantity' => $i['quantidade'],
                        'pp_without_discount' => $i['resposta']['preco_unitario'], 'discount_percent' => 0,
                        'purchase_price' => $i['resposta']['preco_unitario'], 'purchase_price_inc_tax' => $i['resposta']['preco_unitario'],
                        'item_tax' => 0, 'created_at' => $agora, 'updated_at' => $agora,
                    ];
                }
                $totalTxt = self::txt(intdiv($total + 5000, 10000)); // de 10⁻⁸ para 10⁻⁴, arredondado
                $n = $util->setAndGetReferenceCount('purchase_order', $businessId);
                $pedidoId = DB::table('transactions')->insertGetId([
                    'business_id' => $businessId, 'location_id' => (int) $cotacao->location_id, 'contact_id' => $contactId,
                    'type' => 'purchase_order', 'status' => 'ordered',
                    'ref_no' => $util->generateReferenceNumber('purchase_order', $n, $businessId),
                    'transaction_date' => $agora, 'delivery_date' => $agora->copy()->addDays($prazo),
                    'total_before_tax' => $totalTxt, 'final_total' => $totalTxt, 'tax_amount' => 0, 'discount_amount' => 0,
                    'additional_notes' => 'Gerado da cotação '.$cotacao->ref_no,
                    'created_by' => $userId, 'essentials_duration' => 0, 'created_at' => $agora, 'updated_at' => $agora,
                ]);
                DB::table('purchase_lines')->insert(array_map(fn ($l) => $l + ['transaction_id' => $pedidoId], $linhas));
                DB::table('compras_cotacao_itens')->whereIn('id', array_column($itens, 'item_id'))->update(['vencedor_contact_id' => $contactId]);
                $pedidos[] = $pedidoId;
            }

            DB::table('compras_cotacoes')->where('id', $cotacao->id)->update(['status' => 'fechada', 'fechada_em' => $agora, 'updated_at' => $agora]);

            return $pedidos;
        });
    }

    private function aberta(int $businessId, int $cotacaoId): object
    {
        $c = DB::table('compras_cotacoes')->where('business_id', $businessId)->where('id', $cotacaoId)->first(['id', 'status']);
        if ($c === null) {
            throw new InvalidArgumentException('Cotação inválida para este negócio.');
        }
        if ($c->status !== self::ABERTA) {
            throw new InvalidArgumentException('A cotação não está aberta.');
        }

        return $c;
    }

    private static function txt(int $n): string
    {
        return ($n < 0 ? '-' : '').sprintf('%d.%04d', intdiv(abs($n), 10000), abs($n) % 10000);
    }

    /** DECIMAL(22,4) em dez-milésimos inteiros, para comparar preço sem float. */
    private static function dm(string $v): int
    {
        [$i, $f] = array_pad(explode('.', trim($v) === '' ? '0' : trim($v), 2), 2, '');

        return (int) $i * 10000 + (int) substr(str_pad($f, 4, '0'), 0, 4);
    }
}
