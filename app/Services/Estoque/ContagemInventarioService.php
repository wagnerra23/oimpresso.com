<?php

declare(strict_types=1);

namespace App\Services\Estoque;

use App\Events\StockAdjustmentCreatedOrModified;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/**
 * US-ESTOQUE-002 — contagem de inventário (parte 1: abrir, contar, fechar e ver a divergência).
 *
 * Esta classe NÃO mexe no saldo (`variation_location_details`). Ela só registra o que foi
 * contado e calcula a diferença. Aplicar a diferença no estoque é o passo "Aprovar ajuste",
 * que vem em PR próprio, com permissão e prova dupla (regra mestre de estoque).
 *
 * Como a divergência é calculada, e por quê:
 * - Na PRIMEIRA vez que um item é contado, o saldo do sistema naquele local é guardado em
 *   `saldo_no_momento`. Contagens seguintes do mesmo item mudam só a quantidade contada.
 * - Divergência = `qtd_contada − saldo_no_momento` (negativa = falta, positiva = sobra).
 * - Assim o saldo fica "congelado" por item no instante da contagem, sem travar a loja: uma
 *   venda feita depois de contar a prateleira não vira falta falsa.
 *
 * Tier 0 (ADR 0093): toda leitura e escrita filtra pelo `business_id` recebido; a contagem, o
 * local e a variação precisam ser da mesma empresa.
 */
final class ContagemInventarioService
{
    public const MODO_SOMAR = 'somar';
    public const MODO_SUBSTITUIR = 'substituir';

    public function __construct(
        private readonly ProductUtil $productUtil,
        private readonly TransactionUtil $transactionUtil,
    ) {}

    /** Abre uma contagem no local. Só uma contagem aberta por local de cada vez. */
    public function abrir(int $businessId, int $locationId, int $userId, ?string $descricao = null): int
    {
        $localValido = DB::table('business_locations')
            ->where('business_id', $businessId)->where('id', $locationId)->exists();
        if (! $localValido) {
            throw new ContagemInventarioException('local_invalido', 'Local não encontrado nesta empresa.');
        }

        return DB::transaction(function () use ($businessId, $locationId, $userId, $descricao) {
            $aberta = DB::table('estoque_contagens')
                ->where('business_id', $businessId)->where('location_id', $locationId)
                ->where('status', 'aberta')->lockForUpdate()->value('id');
            if ($aberta) {
                throw new ContagemInventarioException('ja_aberta', "Já existe a contagem #{$aberta} aberta neste local.");
            }

            return (int) DB::table('estoque_contagens')->insertGetId([
                'business_id' => $businessId,
                'location_id' => $locationId,
                'descricao' => $descricao,
                'status' => 'aberta',
                'aberta_por' => $userId,
                'aberta_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Acha a variação pelo que o coletor leu: SKU da variação (`sub_sku`) ou SKU do produto
     * (só quando o produto tem uma variação). Devolve null quando não acha ou é ambíguo.
     */
    public function resolverCodigo(int $businessId, string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $porSubSku = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)->whereNull('v.deleted_at')
            ->where('v.sub_sku', $codigo)->pluck('v.id');
        if ($porSubSku->count() === 1) {
            return (int) $porSubSku->first();
        }
        if ($porSubSku->count() > 1) {
            return null;
        }

        $porSkuProduto = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)->whereNull('v.deleted_at')
            ->where('p.sku', $codigo)->pluck('v.id');

        return $porSkuProduto->count() === 1 ? (int) $porSkuProduto->first() : null;
    }

    /**
     * Registra a quantidade contada de uma variação. `somar` acumula (bipar a mesma peça de novo
     * soma), `substituir` grava o número digitado. Devolve a quantidade contada depois do registro.
     */
    public function registrar(int $businessId, int $contagemId, int $variationId, string $quantidade, int $userId, string $modo = self::MODO_SOMAR): string
    {
        if (! in_array($modo, [self::MODO_SOMAR, self::MODO_SUBSTITUIR], true)) {
            throw new ContagemInventarioException('modo_invalido', 'Modo de registro inválido.');
        }
        if (! is_numeric($quantidade) || (float) $quantidade < 0) {
            throw new ContagemInventarioException('quantidade_invalida', 'Quantidade precisa ser um número maior ou igual a zero.');
        }
        $quantidade = number_format((float) $quantidade, 4, '.', '');

        return DB::transaction(function () use ($businessId, $contagemId, $variationId, $quantidade, $userId, $modo) {
            $contagem = DB::table('estoque_contagens')
                ->where('business_id', $businessId)->where('id', $contagemId)
                ->lockForUpdate()->first(['id', 'location_id', 'status']);
            if ($contagem === null) {
                throw new ContagemInventarioException('nao_encontrada', 'Contagem não encontrada.');
            }
            if ($contagem->status !== 'aberta') {
                throw new ContagemInventarioException('nao_aberta', 'Esta contagem não está aberta.');
            }

            $variacao = DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->where('p.business_id', $businessId)->where('v.id', $variationId)->whereNull('v.deleted_at')
                ->first(['v.id', 'v.product_id', 'p.enable_stock']);
            if ($variacao === null) {
                throw new ContagemInventarioException('variacao_invalida', 'Produto não encontrado nesta empresa.');
            }
            if ((int) $variacao->enable_stock !== 1) {
                throw new ContagemInventarioException('sem_controle_estoque', 'Este produto não controla estoque.');
            }

            $item = DB::table('estoque_contagem_itens')
                ->where('contagem_id', $contagemId)->where('variation_id', $variationId)
                ->lockForUpdate()->first(['id', 'qtd_contada']);

            if ($item === null) {
                $saldo = (string) (DB::table('variation_location_details')
                    ->where('variation_id', $variationId)->where('location_id', $contagem->location_id)
                    ->sum('qty_available'));
                DB::table('estoque_contagem_itens')->insert([
                    'business_id' => $businessId,
                    'contagem_id' => $contagemId,
                    'product_id' => (int) $variacao->product_id,
                    'variation_id' => $variationId,
                    'qtd_contada' => $quantidade,
                    'saldo_no_momento' => $saldo,
                    'contado_por' => $userId,
                    'contado_em' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $quantidade;
            }

            // Soma em DECIMAL no MySQL: nenhum float acumulando erro a cada bipe.
            $novo = $modo === self::MODO_SOMAR
                ? DB::raw('qtd_contada + ' . DB::getPdo()->quote($quantidade))
                : $quantidade;
            DB::table('estoque_contagem_itens')->where('id', $item->id)->update([
                'qtd_contada' => $novo,
                'contado_por' => $userId,
                'contado_em' => now(),
                'updated_at' => now(),
            ]);

            return (string) DB::table('estoque_contagem_itens')->where('id', $item->id)->value('qtd_contada');
        });
    }

    /** Fecha a contagem: a partir daqui ninguém conta mais, e a divergência fica pronta para aprovar. */
    public function fechar(int $businessId, int $contagemId, int $userId): void
    {
        $afetadas = DB::table('estoque_contagens')
            ->where('business_id', $businessId)->where('id', $contagemId)->where('status', 'aberta')
            ->update(['status' => 'fechada', 'fechada_por' => $userId, 'fechada_em' => now(), 'updated_at' => now()]);
        if ($afetadas !== 1) {
            throw new ContagemInventarioException('nao_aberta', 'Contagem não encontrada ou já fechada.');
        }
    }

    /**
     * Divergência por item, contada em DECIMAL no MySQL. Valor = diferença × custo de compra
     * padrão da variação (é o custo que o resto do sistema usa hoje; custo médio é outra US).
     *
     * @return list<array{variation_id:int, product_id:int, produto:string, sku:?string, saldo_no_momento:string, qtd_contada:string, diferenca:string, custo_unitario:string, valor_diferenca:string}>
     */
    public function divergencias(int $businessId, int $contagemId, bool $soComDiferenca = false): array
    {
        $q = DB::table('estoque_contagem_itens as i')
            ->join('estoque_contagens as c', 'c.id', '=', 'i.contagem_id')
            ->join('variations as v', 'v.id', '=', 'i.variation_id')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('c.business_id', $businessId)->where('i.business_id', $businessId)
            ->where('i.contagem_id', $contagemId)
            ->orderBy('p.name')
            ->selectRaw('i.variation_id, i.product_id, p.name as produto, v.sub_sku as sku,
                i.saldo_no_momento, i.qtd_contada,
                CAST(i.qtd_contada - i.saldo_no_momento AS DECIMAL(22,4)) as diferenca,
                CAST(COALESCE(v.default_purchase_price, 0) AS DECIMAL(22,4)) as custo_unitario,
                CAST(ROUND((i.qtd_contada - i.saldo_no_momento) * COALESCE(v.default_purchase_price, 0), 2) AS DECIMAL(22,2)) as valor_diferenca');
        if ($soComDiferenca) {
            $q->whereRaw('i.qtd_contada <> i.saldo_no_momento');
        }

        return $q->get()->map(fn ($r) => [
            'variation_id' => (int) $r->variation_id,
            'product_id' => (int) $r->product_id,
            'produto' => (string) $r->produto,
            'sku' => $r->sku,
            'saldo_no_momento' => (string) $r->saldo_no_momento,
            'qtd_contada' => (string) $r->qtd_contada,
            'diferenca' => (string) $r->diferenca,
            'custo_unitario' => (string) $r->custo_unitario,
            'valor_diferenca' => (string) $r->valor_diferenca,
        ])->all();
    }

    /**
     * Aprova o ajuste de uma contagem FECHADA (parte 2). Escreve no estoque — regra mestre.
     *
     * - FALTA (contado < saldo guardado): um ajuste de estoque (`stock_adjustment`, o mesmo do
     *   StockAdjustmentController), uma linha por item, ao custo de compra padrão; tira do saldo e
     *   amarra no lote de compra (FIFO/LIFO) como o ajuste manual faz.
     * - SOBRA (contado > saldo guardado): uma entrada `opening_stock` SEM produto dono
     *   (`opening_stock_product_id` nulo), com uma linha de compra por item ao custo de compra.
     *   O lote existe para as vendas seguintes casarem no FIFO, e a tela de estoque inicial do
     *   produto (que filtra por `opening_stock_product_id`) não vê nem apaga esta entrada.
     * - A diferença é aplicada sobre o saldo de AGORA: o que foi vendido depois da contagem já
     *   saiu do saldo e não é cobrado de novo.
     * - Tudo numa transação; a contagem vira `ajustada` e guarda os dois movimentos.
     *
     * @return array{ajuste_transaction_id:?int, entrada_transaction_id:?int, itens_falta:int, itens_sobra:int, valor_falta:string, valor_sobra:string}
     */
    public function aprovar(int $businessId, int $contagemId, int $userId): array
    {
        return DB::transaction(function () use ($businessId, $contagemId, $userId) {
            $contagem = DB::table('estoque_contagens')
                ->where('business_id', $businessId)->where('id', $contagemId)
                ->lockForUpdate()->first(['id', 'location_id', 'status']);
            if ($contagem === null) {
                throw new ContagemInventarioException('nao_encontrada', 'Contagem não encontrada.');
            }
            if ($contagem->status !== 'fechada') {
                throw new ContagemInventarioException('nao_fechada', 'Só uma contagem fechada pode ter o ajuste aprovado.');
            }

            $itens = $this->divergencias($businessId, $contagemId, true);
            $faltas = array_values(array_filter($itens, fn ($i) => (float) $i['diferenca'] < 0));
            $sobras = array_values(array_filter($itens, fn ($i) => (float) $i['diferenca'] > 0));

            // Totais em DECIMAL no MySQL (nenhum float somando dinheiro).
            $totais = DB::selectOne(
                'SELECT
                    CAST(COALESCE(SUM(IF(i.qtd_contada < i.saldo_no_momento, ROUND((i.saldo_no_momento - i.qtd_contada) * COALESCE(v.default_purchase_price, 0), 2), 0)), 0) AS DECIMAL(22,2)) AS falta,
                    CAST(COALESCE(SUM(IF(i.qtd_contada > i.saldo_no_momento, ROUND((i.qtd_contada - i.saldo_no_momento) * COALESCE(v.default_purchase_price, 0), 2), 0)), 0) AS DECIMAL(22,2)) AS sobra
                 FROM estoque_contagem_itens i JOIN variations v ON v.id = i.variation_id
                 WHERE i.contagem_id = ? AND i.business_id = ?',
                [$contagemId, $businessId],
            );

            $agora = now()->format('Y-m-d H:i:s');
            $nota = 'Contagem de inventário #' . $contagemId;
            $ajusteId = null;
            $entradaId = null;

            if ($faltas !== []) {
                $ref = $this->transactionUtil->setAndGetReferenceCount('stock_adjustment', $businessId);
                $ajuste = Transaction::create([
                    'business_id' => $businessId,
                    'location_id' => $contagem->location_id,
                    'type' => 'stock_adjustment',
                    'adjustment_type' => 'normal',
                    'transaction_date' => $agora,
                    'ref_no' => $this->transactionUtil->generateReferenceNumber('stock_adjustment', $ref, $businessId),
                    'final_total' => (string) $totais->falta,
                    'total_amount_recovered' => 0,
                    'additional_notes' => $nota . ' — falta',
                    'created_by' => $userId,
                ]);
                $linhas = [];
                foreach ($faltas as $i) {
                    $qtd = ltrim($i['diferenca'], '-');
                    $linhas[] = [
                        'product_id' => $i['product_id'],
                        'variation_id' => $i['variation_id'],
                        'quantity' => $qtd,
                        'unit_price' => $i['custo_unitario'],
                    ];
                    $this->productUtil->decreaseProductQuantity($i['product_id'], $i['variation_id'], (int) $contagem->location_id, $qtd);
                }
                $ajuste->stock_adjustment_lines()->createMany($linhas);

                $biz = DB::table('business')->where('id', $businessId)->first(['id', 'accounting_method', 'pos_settings']);
                $this->transactionUtil->mapPurchaseSell(
                    ['id' => $businessId, 'accounting_method' => $biz->accounting_method, 'location_id' => (int) $contagem->location_id, 'pos_settings' => $biz->pos_settings],
                    $ajuste->stock_adjustment_lines()->get(),
                    'stock_adjustment',
                );
                event(new StockAdjustmentCreatedOrModified($ajuste, 'added'));
                $ajusteId = (int) $ajuste->id;
            }

            if ($sobras !== []) {
                $entrada = Transaction::create([
                    'business_id' => $businessId,
                    'location_id' => $contagem->location_id,
                    'type' => 'opening_stock',
                    'opening_stock_product_id' => null,
                    'status' => 'received',
                    'payment_status' => 'paid',
                    'transaction_date' => $agora,
                    'total_before_tax' => (string) $totais->sobra,
                    'final_total' => (string) $totais->sobra,
                    'additional_notes' => $nota . ' — sobra',
                    'created_by' => $userId,
                ]);
                $linhas = [];
                foreach ($sobras as $i) {
                    $linhas[] = new PurchaseLine([
                        'product_id' => $i['product_id'],
                        'variation_id' => $i['variation_id'],
                        'quantity' => $i['diferenca'],
                        'pp_without_discount' => $i['custo_unitario'],
                        'purchase_price' => $i['custo_unitario'],
                        'purchase_price_inc_tax' => $i['custo_unitario'],
                        'item_tax' => 0,
                    ]);
                    $this->productUtil->updateProductQuantity((int) $contagem->location_id, $i['product_id'], $i['variation_id'], $i['diferenca'], 0, null, false);
                }
                $entrada->purchase_lines()->saveMany($linhas);
                $entradaId = (int) $entrada->id;
            }

            DB::table('estoque_contagens')->where('id', $contagemId)->update([
                'status' => 'ajustada',
                'ajustada_por' => $userId,
                'ajustada_em' => $agora,
                'ajuste_transaction_id' => $ajusteId,
                'entrada_transaction_id' => $entradaId,
                'updated_at' => $agora,
            ]);

            return [
                'ajuste_transaction_id' => $ajusteId,
                'entrada_transaction_id' => $entradaId,
                'itens_falta' => count($faltas),
                'itens_sobra' => count($sobras),
                'valor_falta' => (string) $totais->falta,
                'valor_sobra' => (string) $totais->sobra,
            ];
        });
    }
}
