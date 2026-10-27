<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\Contracts\Compras\CustoDoRecebimento;
use App\Events\PurchaseCreatedOrModified;
use App\Product;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Variation;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeDfeItem;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\Entrada\LeitorNfeEntrada;

/**
 * NF-e do fornecedor vira compra (US-COM-003 · ADR `proposals/2026-10-06-entrada-xml-compra.md`
 * D1/D3/D4, fase 3).
 *
 * A compra nasce `type=purchase`, `status=received`, pelas mesmas peças do PurchaseController::store:
 * `createOrUpdatePurchaseLines` (estoque), regra de custo da empresa (US-COM-026),
 * `updatePaymentStatus`, `adjustStockOverSelling` e `PurchaseCreatedOrModified` (título a pagar pelo
 * observer do Financeiro). Nenhum caminho paralelo de estoque ou de título.
 *
 * Valor (regra mestre de cálculo, `memory/proibicoes.md`):
 *  - linha: quantidade = qCom, custo unitário = vProd / qCom (o ICMS é "por dentro" do vProd; no
 *    Simples ele não gera crédito e fica no custo — D4);
 *  - compra: total_before_tax = Σ vProd · desconto fixo = vDesc · frete = vFrete · despesa extra =
 *    vIPI + vST + vSeg + vOutro · final_total = vNF. Se os componentes não somarem o vNF (±0,01)
 *    a importação é recusada: são duas contas independentes que precisam bater.
 *  - regime normal (crédito de ICMS fora do custo) é recusado até a regra ter dupla prova e [W].
 *
 * Item sem vínculo não vira compra (D3): o vínculo vem da tela (uma vez) e fica lembrado em
 * `compras_produto_fornecedor`. Tier 0 (ADR 0093): nota, fornecedor, variação e local são da empresa.
 *
 * Roda em requisição web: `createOrUpdatePurchaseLines` lê a moeda da sessão.
 */
final class ImportarDfeComoCompraService
{
    /** Valor do vínculo que pede "crie o produto": nenhum produto da empresa serve. */
    public const PRODUTO_NOVO = 'novo';

    public function __construct(
        private readonly ProductUtil $productUtil,
        private readonly TransactionUtil $transactionUtil,
    ) {}

    /**
     * @param  array<int, int|string>  $vinculos  id do item da nota → variation_id escolhido, ou PRODUTO_NOVO
     *
     * @throws CompraDaNotaRecusadaException
     */
    public function executar(int $businessId, int $dfeId, int $userId, int $locationId, array $vinculos = []): Transaction
    {
        if (! config('nfebrasil.entrada_xml_enabled')) {
            throw new CompraDaNotaRecusadaException('A entrada por XML está desligada para esta instalação.');
        }

        return DB::transaction(function () use ($businessId, $dfeId, $userId, $locationId, $vinculos) {
            // Trava a nota: dois usuários importando a mesma DF-e não geram duas compras (R-COM-206).
            $dfe = NfeDfeRecebido::where('business_id', $businessId)->whereKey($dfeId)->lockForUpdate()->first()
                ?? throw new CompraDaNotaRecusadaException('Nota não encontrada nesta empresa.');
            if ($dfe->transaction_id) {
                throw new CompraDaNotaRecusadaException('Esta nota já virou a compra #' . $dfe->transaction_id . '.');
            }

            $regime = DB::table('nfe_business_configs')->where('business_id', $businessId)->value('regime');
            if (! in_array($regime, [null, 'simples', 'mei'], true)) {
                throw new CompraDaNotaRecusadaException('No regime normal o crédito de ICMS sai do custo — essa regra ainda não está pronta.');
            }
            if (! DB::table('business_locations')->where('business_id', $businessId)->where('id', $locationId)->exists()) {
                throw new CompraDaNotaRecusadaException('Local de estoque não encontrado nesta empresa.');
            }

            $fornecedorId = $this->fornecedor($businessId, (string) $dfe->cnpj_emitente)
                ?? throw new CompraDaNotaRecusadaException('Fornecedor com CNPJ ' . $dfe->cnpj_emitente . ' não está cadastrado.');

            $nota = $this->lerXml($dfe);
            $linhas = $this->linhas($businessId, $dfe, $vinculos, $userId);
            $t = $nota['totais'];
            $extras = round($t['ipi'] + $t['st'] + $t['seguro'] + $t['outras'], 2);
            $somaItens = round(array_sum(array_column($linhas, 'vProd')), 2);
            if (abs($somaItens - $t['produtos']) > 0.01
                || abs(round($t['produtos'] - $t['desconto'] + $t['frete'] + $extras, 2) - $nota['valor_total']) > 0.01) {
                throw new CompraDaNotaRecusadaException('Os totais da nota não fecham com os itens; confira o XML.');
            }

            $compra = Transaction::create([
                'business_id' => $businessId, 'location_id' => $locationId, 'contact_id' => $fornecedorId,
                'type' => 'purchase', 'status' => 'received', 'payment_status' => 'due', 'created_by' => $userId,
                'ref_no' => $this->productUtil->generateReferenceNumber('purchase', $this->productUtil->setAndGetReferenceCount('purchase', $businessId), $businessId),
                'transaction_date' => $dfe->data_emissao ?? now(), 'exchange_rate' => 1,
                'total_before_tax' => $somaItens, 'tax_amount' => 0,
                'discount_type' => 'fixed', 'discount_amount' => $t['desconto'], 'shipping_charges' => $t['frete'],
                'additional_expense_key_1' => $extras > 0 ? 'IPI, ST e outras da NF-e' : null,
                'additional_expense_value_1' => $extras,
                'final_total' => $nota['valor_total'],
                'additional_notes' => 'NF-e ' . $dfe->chave_44,
            ]);

            $custo = app()->bound(CustoDoRecebimento::class) ? app(CustoDoRecebimento::class) : null;
            $custo = $custo?->assume($businessId) ? $custo : null;
            $editar = $custo ? 0 : (int) DB::table('business')->where('id', $businessId)->value('enable_editing_product_from_purchase');
            $this->productUtil->createOrUpdatePurchaseLines($compra, array_column($linhas, 'linha'), $this->transactionUtil->purchaseCurrencyDetails($businessId), $editar);
            $custo?->aoReceber($compra);

            $this->transactionUtil->updatePaymentStatus($compra->id, $compra->final_total);
            $this->productUtil->adjustStockOverSelling($compra);
            $this->transactionUtil->activityLog($compra, 'added');
            PurchaseCreatedOrModified::dispatch($compra);

            $dfe->update(['transaction_id' => $compra->id]);

            return $compra;
        });
    }

    /** @return list<array{vProd: float, linha: array<string, mixed>}> */
    private function linhas(int $businessId, NfeDfeRecebido $dfe, array $vinculos, int $userId): array
    {
        $itens = NfeDfeItem::where('business_id', $businessId)->where('dfe_recebido_id', $dfe->id)->orderBy('id')->get();
        if ($itens->isEmpty()) {
            throw new CompraDaNotaRecusadaException('A nota não tem itens (só o resumo chegou); envie o XML completo.');
        }

        $faltam = [];
        $linhas = [];
        foreach ($itens as $item) {
            if (($vinculos[$item->id] ?? null) === self::PRODUTO_NOVO) {
                $vinculos[$item->id] = $this->criarProduto($businessId, $item, $userId);
            }
            $variationId = $vinculos[$item->id] ?? $item->variation_id ?? $this->lembrado($businessId, $dfe->cnpj_emitente, $item->codigo_fornecedor);
            $produtoId = $variationId ? DB::table('variations')->join('products', 'products.id', '=', 'variations.product_id')
                ->where('products.business_id', $businessId)->where('variations.id', $variationId)->value('products.id') : null;
            if (! $produtoId || (float) $item->quantidade <= 0) {
                $faltam[] = $item->descricao;
                continue;
            }
            if (isset($vinculos[$item->id]) && $item->codigo_fornecedor) {
                DB::table('compras_produto_fornecedor')->updateOrInsert(
                    ['business_id' => $businessId, 'cnpj_fornecedor' => $dfe->cnpj_emitente, 'codigo_fornecedor' => $item->codigo_fornecedor],
                    ['variation_id' => $variationId, 'created_by' => $userId, 'updated_at' => now(), 'created_at' => now()],
                );
            }
            $item->update(['variation_id' => $variationId]);

            // Strings em pt-BR sem milhar: createOrUpdatePurchaseLines passa tudo por num_uf.
            $unit = number_format((float) $item->valor_total / (float) $item->quantidade, 4, ',', '');
            $linhas[] = ['vProd' => (float) $item->valor_total, 'linha' => [
                'product_id' => $produtoId, 'variation_id' => $variationId,
                'quantity' => number_format((float) $item->quantidade, 4, ',', ''),
                'pp_without_discount' => $unit, 'discount_percent' => '0', 'purchase_price' => $unit,
                'purchase_price_inc_tax' => $unit, 'item_tax' => '0', 'purchase_line_tax_id' => null,
            ]];
        }
        if ($faltam) {
            throw new CompraDaNotaRecusadaException('Vincule estes itens a um produto antes: ' . implode(' · ', $faltam));
        }

        return $linhas;
    }

    /**
     * Item marcado "produto novo" na conferência: cria o produto simples da empresa com o nome do
     * item, a unidade do XML (quando existe uma com a mesma sigla) e o custo da nota.
     *
     * Nasce **fora de venda** (`not_for_selling=1`) e com preço de venda 0: o preço é decisão de
     * quem vende, e um produto à venda por R$ 0 seria pior que um produto que ainda não vende.
     * Sem local da empresa vinculado, o produto não aparece em nenhum PDV até alguém liberar.
     */
    private function criarProduto(int $businessId, NfeDfeItem $item, int $userId): int
    {
        $unidade = DB::table('units')->where('business_id', $businessId)->whereNull('deleted_at')
            ->whereRaw('LOWER(short_name) = ?', [mb_strtolower((string) $item->unidade)])->value('id')
            ?? DB::table('units')->where('business_id', $businessId)->whereNull('deleted_at')->orderBy('id')->value('id')
            ?? throw new CompraDaNotaRecusadaException('A empresa não tem unidade de medida cadastrada para criar o produto.');

        $produto = Product::create([
            'business_id' => $businessId, 'name' => mb_substr($item->descricao, 0, 191), 'type' => 'single',
            'unit_id' => $unidade, 'enable_stock' => 1, 'not_for_selling' => 1, 'tax_type' => 'exclusive',
            'barcode_type' => 'C128', 'sku' => ' ', 'alert_quantity' => 0, 'created_by' => $userId,
        ]);
        $produto->sku = $this->productUtil->generateProductSku($produto->id);
        $produto->save();

        $custo = number_format((float) $item->valor_total / max((float) $item->quantidade, 0.0001), 4, ',', '');
        $this->productUtil->createSingleProductVariation($produto->id, $produto->sku, $custo, $custo, '0', '0', '0');

        return (int) Variation::where('product_id', $produto->id)->value('id');
    }

    private function lembrado(int $businessId, string $cnpj, ?string $codigo): ?int
    {
        return $codigo === null ? null : DB::table('compras_produto_fornecedor')
            ->where('business_id', $businessId)->where('cnpj_fornecedor', $cnpj)->where('codigo_fornecedor', $codigo)
            ->value('variation_id');
    }

    private function fornecedor(int $businessId, string $cnpj): ?int
    {
        return DB::table('contacts')->where('business_id', $businessId)->whereIn('type', ['supplier', 'both'])
            ->whereNull('deleted_at')
            ->whereRaw("REPLACE(REPLACE(REPLACE(tax_number, '.', ''), '/', ''), '-', '') = ?", [$cnpj])
            ->value('id');
    }

    private function lerXml(NfeDfeRecebido $dfe): array
    {
        $xml = $dfe->xml_path ? Storage::disk(config('nfebrasil.dfes_recebidos_disk', 'local'))->get($dfe->xml_path) : null;
        if (! $xml) {
            throw new CompraDaNotaRecusadaException('O XML da nota não está guardado; envie o arquivo de novo.');
        }

        try {
            return LeitorNfeEntrada::ler($xml);
        } catch (\InvalidArgumentException $e) {
            throw new CompraDaNotaRecusadaException($e->getMessage());
        }
    }
}
