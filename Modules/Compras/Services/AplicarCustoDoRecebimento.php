<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use App\Contracts\Compras\CustoDoRecebimento;
use App\Domain\Fsm\Modelos\RegrasDaEmpresa;
use App\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Compra recebida atualiza o custo e trata o preço pela regra da empresa (UC-FLX-17 · US-COM-026).
 *
 * Só age quando a empresa GRAVOU a regra "Atualizar o custo dos insumos" (RegrasDaEmpresa::custoGravado),
 * ligada e disparando em Recebido. Empresa que nunca gravou a regra: nada muda, o core segue gravando o
 * último preço quando `business.enable_editing_product_from_purchase` está ligado.
 *
 * Quando assume, o PurchaseController desliga a edição de produto pela compra do core
 * (ProductUtil::updateProductFromPurchase) para os dois não gravarem `dpp` um por cima do outro, e chama
 * `aoReceber` ao entrar em `received` (criar já recebida, editar para recebida, mudar o status).
 *
 * Por variação da compra:
 *  - custo = CustoDoInsumoService::naData na data da compra, pelo `metodo` da regra (imposto incluso,
 *    por unidade base). Custo nulo: a variação não muda.
 *    `dpp_inc_tax` = custo · `default_purchase_price` = custo × 100 / (100 + imposto do produto).
 *  - `preco`:
 *     so_custo — preço igual; a margem é recalculada: (preço sem imposto − custo) / custo × 100.
 *     aplicar  — preço sem imposto = custo sem imposto × (1 + margem atual / 100);
 *                preço com imposto = preço sem imposto × (100 + imposto) / 100. A margem fica.
 *     propor   — o mesmo preço do `aplicar` vai para `compras_propostas_preco` (pendente); o preço
 *                fica igual e a margem é recalculada como no so_custo.
 * Arredondamento: 4 casas, como as colunas de `variations`.
 *
 * Tier 0 (ADR 0093): variação só da empresa da compra (join em `products.business_id`).
 */
final class AplicarCustoDoRecebimento implements CustoDoRecebimento
{
    public function __construct(
        private readonly RegrasDaEmpresa $regras,
        private readonly CustoDoInsumoService $custo,
    ) {}

    /** @return array<string, mixed>|null os parâmetros da regra, se ela assume o recebimento */
    public function regra(int $businessId): ?array
    {
        $r = $this->regras->custoGravado($businessId);
        if ($r === null || ! $r['ativo'] || ($r['params']['etapa'] ?? null) !== 'recebido') {
            return null;
        }

        return $r['params'];
    }

    public function assume(int $businessId): bool
    {
        return $this->regra($businessId) !== null;
    }

    public function aoReceber(Transaction $compra): array
    {
        $biz = (int) $compra->business_id;
        if ($compra->type !== 'purchase' || $compra->status !== 'received') {
            return [];
        }
        $params = $this->regra($biz);
        if ($params === null) {
            return [];
        }

        $ids = DB::table('purchase_lines')->where('transaction_id', $compra->id)->distinct()->pluck('variation_id');
        $feito = [];
        foreach ($ids as $variationId) {
            $v = DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->leftJoin('tax_rates as tr', 'tr.id', '=', 'p.tax')
                ->where('p.business_id', $biz)
                ->where('v.id', $variationId)
                ->select('v.id', 'v.dpp_inc_tax', 'v.default_sell_price', 'v.sell_price_inc_tax', 'v.profit_percent', 'tr.amount as imposto')
                ->first();
            if ($v === null) {
                continue;
            }
            $r = $this->custo->naData($biz, (int) $v->id, (string) $compra->transaction_date, (string) $params['metodo']);
            if ($r['custo'] === null) {
                continue;
            }

            $imposto = (float) ($v->imposto ?? 0);
            $custoInc = (float) $r['custo'];
            $custoExc = round($custoInc * 100 / (100 + $imposto), 4);
            $vendaExc = round($custoExc * (1 + (float) $v->profit_percent / 100), 4);
            $vendaInc = round($vendaExc * (100 + $imposto) / 100, 4);
            $margem = $custoExc > 0 ? round(((float) $v->default_sell_price - $custoExc) / $custoExc * 100, 4) : 0.0;

            $dados = ['default_purchase_price' => $custoExc, 'dpp_inc_tax' => $custoInc, 'profit_percent' => $margem, 'updated_at' => now()];
            if ($params['preco'] === 'aplicar') {
                $dados = ['default_sell_price' => $vendaExc, 'sell_price_inc_tax' => $vendaInc, 'profit_percent' => (float) $v->profit_percent] + $dados;
            }
            DB::table('variations')->where('id', $v->id)->update($dados);

            $proposta = null;
            if ($params['preco'] === 'propor' && abs($vendaInc - (float) $v->sell_price_inc_tax) >= 0.0001) {
                $proposta = DB::table('compras_propostas_preco')->insertGetId([
                    'business_id' => $biz, 'variation_id' => $v->id, 'transaction_id' => $compra->id,
                    'custo_antes' => $v->dpp_inc_tax, 'custo_depois' => $custoInc,
                    'preco_antes' => $v->sell_price_inc_tax, 'preco_proposto' => $vendaInc,
                    'status' => 'pendente', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $feito[] = [
                'variation_id' => (int) $v->id, 'preco' => $params['preco'],
                'custo_antes' => (float) $v->dpp_inc_tax, 'custo_depois' => $custoInc,
                'preco_antes' => (float) $v->sell_price_inc_tax,
                'preco_depois' => $params['preco'] === 'aplicar' ? $vendaInc : (float) $v->sell_price_inc_tax,
                'proposta_id' => $proposta,
            ];
        }

        return $feito;
    }
}
