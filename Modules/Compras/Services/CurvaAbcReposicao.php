<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Curva ABC e sugestão de reposição pelo giro, por variação da empresa (US-ESTOQUE-003).
 *
 * Só LÊ. Não muda valor nem estoque. Período padrão: os últimos 90 dias até hoje.
 *
 *   vendido      = Σ (quantidade − devolvida) das vendas finalizadas no período
 *   faturamento  = Σ vendido × `unit_price_inc_tax`
 *   custo        = Σ custo do lote de cada venda (`transaction_sell_lines_purchase_lines` →
 *                  `purchase_lines.purchase_price_inc_tax`), o mesmo do relatório de lucro.
 *                  Venda sem lote amarrado: `custo_completo` = false e a margem fica nula.
 *   margem       = faturamento − custo
 *   curva        = ordena por faturamento (e, à parte, por margem); A até 80% acumulado, B até 95%, C o resto.
 *                  Margem nula ou negativa é C na curva de margem.
 *
 *   giro/dia     = vendido / dias do período
 *   estoque      = saldo da empresa nos seus locais (`variation_location_details`)
 *   a caminho    = compras `ordered`/`pending` + o que falta dos pedidos de compra abertos
 *   prazo        = dias do fornecedor da última compra (`compras_prazo_fornecedor`); sem cadastro, 0
 *   cobertura    = estoque / giro (dias); sem venda, nula
 *   sugestão     = giro × (prazo + dias de cobertura desejados) − estoque − a caminho, arredondada para
 *                  cima; nunca negativa.
 *
 * Diferença do MRP (NecessidadeDeCompra, US-COM-030): o MRP olha o que as OS reservaram e o estoque
 * mínimo, por local; aqui é a velocidade de venda da empresa inteira. As duas listas podem citar o
 * mesmo item por razões diferentes.
 *
 * Tier 0 (ADR 0093): vendas, compras, produtos e locais filtrados pelo `$businessId`.
 */
final class CurvaAbcReposicao
{
    public const LIMITE_A = 80.0;

    public const LIMITE_B = 95.0;

    public function __construct(private readonly RequisicaoEstoqueMinimoService $requisicao) {}

    public static function ligado(): bool
    {
        return (bool) config('compras.curva_abc_reposicao', false);
    }

    /**
     * @return array{de:string, ate:string, dias:int, cobertura_dias:int, itens:list<array<string, mixed>>, totais:array<string, float>}
     */
    public function calcular(int $businessId, ?Carbon $de = null, ?Carbon $ate = null, int $coberturaDias = 30): array
    {
        $ate = ($ate ?? Carbon::today())->copy()->endOfDay();
        $de = ($de ?? $ate->copy()->subDays(89))->copy()->startOfDay();
        $dias = max(1, (int) $de->copy()->diffInDays($ate->copy()->startOfDay()) + 1);

        $custoLote = DB::table('transaction_sell_lines_purchase_lines as m')
            ->join('purchase_lines as pl', 'pl.id', '=', 'm.purchase_line_id')
            ->whereNotNull('m.sell_line_id')
            ->groupBy('m.sell_line_id')
            ->selectRaw('m.sell_line_id, SUM(m.quantity - m.qty_returned) as qtd, SUM((m.quantity - m.qty_returned) * pl.purchase_price_inc_tax) as custo');

        $vendas = DB::table('transaction_sell_lines as l')
            ->join('transactions as t', 't.id', '=', 'l.transaction_id')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoinSub($custoLote, 'c', 'c.sell_line_id', '=', 'l.id')
            ->where('t.business_id', $businessId)->where('p.business_id', $businessId)
            ->where('t.type', 'sell')->where('t.status', 'final')
            ->whereBetween('t.transaction_date', [$de, $ate])
            ->whereNull('l.parent_sell_line_id')
            ->groupBy('l.variation_id', 'p.id', 'p.name', 'v.sub_sku', 'v.name', 'p.type')
            ->selectRaw('l.variation_id, p.id as product_id, p.name, v.sub_sku, v.name as var_nome, p.type,
                SUM(l.quantity - l.quantity_returned) as vendido,
                SUM((l.quantity - l.quantity_returned) * l.unit_price_inc_tax) as faturamento,
                SUM(COALESCE(c.custo, 0)) as custo,
                SUM(CASE WHEN COALESCE(c.qtd, 0) + 0.00005 >= (l.quantity - l.quantity_returned) THEN 0 ELSE 1 END) as sem_lote')
            ->get();

        $itens = [];
        foreach ($vendas as $r) {
            $vendido = round((float) $r->vendido, 4);
            if ($vendido <= 0) {
                continue;
            }
            $completo = (int) $r->sem_lote === 0;
            $fat = round((float) $r->faturamento, 2);
            $custo = $completo ? round((float) $r->custo, 2) : null;
            $itens[(int) $r->variation_id] = [
                'variation_id' => (int) $r->variation_id, 'product_id' => (int) $r->product_id,
                'nome' => $r->type === 'variable' ? $r->name.' — '.$r->var_nome : (string) $r->name,
                'sku' => (string) $r->sub_sku, 'vendido' => $vendido, 'faturamento' => $fat,
                'custo' => $custo, 'custo_completo' => $completo,
                'margem' => $custo !== null ? round($fat - $custo, 2) : null,
                'margem_pct' => $custo !== null && $fat > 0 ? round(($fat - $custo) / $fat * 100, 1) : null,
            ];
        }

        $this->classificar($itens, 'faturamento', 'curva');
        $this->classificar($itens, 'margem', 'curva_margem');

        $estoque = $this->porVariacao(DB::table('variation_location_details as vld')
            ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
            ->where('bl.business_id', $businessId)->whereIn('vld.variation_id', array_keys($itens) ?: [0])
            ->groupBy('vld.variation_id')->selectRaw('vld.variation_id, SUM(vld.qty_available) as q')->get());
        $compras = $this->porVariacao(DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)->where('t.type', 'purchase')->whereIn('t.status', ['ordered', 'pending'])
            ->whereIn('pl.variation_id', array_keys($itens) ?: [0])
            ->groupBy('pl.variation_id')->selectRaw('pl.variation_id, SUM(pl.quantity - pl.quantity_returned) as q')->get());
        $pedidos = $this->porVariacao(DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)->where('t.type', 'purchase_order')->whereIn('t.status', ['ordered', 'partial'])
            ->whereIn('pl.variation_id', array_keys($itens) ?: [0])->whereRaw('pl.quantity > pl.po_quantity_purchased')
            ->groupBy('pl.variation_id')->selectRaw('pl.variation_id, SUM(pl.quantity - pl.po_quantity_purchased) as q')->get());

        foreach ($itens as $vid => &$i) {
            $giro = $i['vendido'] / $dias;
            $saldo = $estoque[$vid] ?? 0.0;
            $caminho = ($compras[$vid] ?? 0.0) + ($pedidos[$vid] ?? 0.0);
            $fornecedor = $this->requisicao->ultimaCompra($businessId, $vid)?->contact_id;
            $prazo = $fornecedor === null ? null : DB::table('compras_prazo_fornecedor')
                ->where('business_id', $businessId)->where('product_id', $i['product_id'])->where('contact_id', $fornecedor)->value('dias');

            $i['giro_dia'] = round($giro, 4);
            $i['estoque'] = round($saldo, 4);
            $i['a_caminho'] = round($caminho, 4);
            $i['fornecedor_id'] = $fornecedor !== null ? (int) $fornecedor : null;
            $i['prazo_dias'] = $prazo !== null ? (int) $prazo : null;
            $i['cobertura_dias'] = $giro > 0 ? round($saldo / $giro, 1) : null;
            $i['sugestao'] = self::sugestao($giro, (int) ($prazo ?? 0), $coberturaDias, $saldo, $caminho);
        }
        unset($i);

        $lista = array_values($itens);
        usort($lista, fn ($a, $b) => [$b['faturamento'], $a['variation_id']] <=> [$a['faturamento'], $b['variation_id']]);

        return [
            'de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'dias' => $dias,
            'cobertura_dias' => $coberturaDias, 'itens' => $lista,
            'totais' => [
                'faturamento' => round(array_sum(array_column($lista, 'faturamento')), 2),
                'itens_a' => count(array_filter($lista, fn ($x) => $x['curva'] === 'A')),
                'a_repor' => count(array_filter($lista, fn ($x) => $x['sugestao'] > 0)),
            ],
        ];
    }

    /** Quanto comprar: giro × (prazo + cobertura) − estoque − a caminho, para cima, nunca negativo. */
    public static function sugestao(float $giroDia, int $prazoDias, int $coberturaDias, float $estoque, float $aCaminho): float
    {
        $falta = $giroDia * ($prazoDias + $coberturaDias) - $estoque - $aCaminho;

        return $falta > 0 ? (float) ceil(round($falta, 4)) : 0.0;
    }

    /** @param array<int, array<string, mixed>> $itens */
    private function classificar(array &$itens, string $campo, string $destino): void
    {
        $ordem = array_keys($itens);
        usort($ordem, fn ($a, $b) => [(float) ($itens[$b][$campo] ?? 0), $a] <=> [(float) ($itens[$a][$campo] ?? 0), $b]);
        $total = array_sum(array_map(fn ($k) => max(0.0, (float) ($itens[$k][$campo] ?? 0)), $ordem));
        $acumulado = 0.0;
        foreach ($ordem as $k) {
            $valor = (float) ($itens[$k][$campo] ?? 0);
            if ($itens[$k][$campo] === null || $valor <= 0 || $total <= 0) {
                $itens[$k][$destino] = 'C';

                continue;
            }
            // A classe é decidida pelo acumulado ANTES do item: o item que cruza os 80% ainda é A.
            $itens[$k][$destino] = $acumulado < self::LIMITE_A ? 'A' : ($acumulado < self::LIMITE_B ? 'B' : 'C');
            $acumulado += $valor / $total * 100;
        }
    }

    /** @return array<int, float> */
    private function porVariacao(iterable $linhas): array
    {
        $saida = [];
        foreach ($linhas as $l) {
            $saida[(int) $l->variation_id] = (float) $l->q;
        }

        return $saida;
    }
}
