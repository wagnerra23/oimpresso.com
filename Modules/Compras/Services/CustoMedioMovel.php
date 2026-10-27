<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use Illuminate\Support\Facades\DB;

/**
 * Custo médio ponderado móvel por variação (US-PROD-024 fase 2).
 *
 * A cada compra recebida, por variação:
 *
 *   qtd antes   = estoque da empresa agora − quantidade desta compra (o estoque já subiu); nunca < 0
 *   custo antes = o custo médio guardado; sem linha guardada, a semente (custo do estoque restante)
 *   depois      = (custo antes × qtd antes + valor da entrada) / (qtd antes + qtd entrada)
 *
 * Valor da entrada = Σ quantidade × `purchase_price_inc_tax` das linhas da compra (por unidade base,
 * imposto incluso, como `dpp_inc_tax`). Sem estoque antes (ou sem custo antes) o médio é o custo da
 * entrada. Arredondamento: 4 casas, como `variations`.
 *
 * Difere do método `medio` do CustoDoInsumoService (média de TODAS as compras até a data): aqui o
 * que já saiu do estoque deixa de pesar. Ex.: compra 10 a 10,00, vende 10, compra 10 a 20,00 →
 * móvel 20,00; média das compras 15,00.
 *
 * Só escreve nas tabelas próprias (`compras_custo_medio*`). Não muda `variations`, preço nem estoque.
 * Idempotente: a mesma (compra, variação) não entra duas vezes.
 *
 * Tier 0 (ADR 0093): variação só da empresa da compra (join em `products.business_id`); estoque
 * somado só nos locais da empresa.
 */
final class CustoMedioMovel implements \App\Contracts\Compras\CustoMedio
{
    public function ligado(): bool
    {
        return (bool) config('compras.custo_medio_movel', false);
    }

    /** @return list<array<string, mixed>> os recálculos feitos */
    public function registrarEntrada(int $transactionId): array
    {
        $tx = DB::table('transactions')->where('id', $transactionId)->first(['id', 'business_id', 'type', 'status']);
        if ($tx === null || $tx->type !== 'purchase' || $tx->status !== 'received') {
            return [];
        }
        $biz = (int) $tx->business_id;

        $entradas = DB::table('purchase_lines as pl')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('pl.transaction_id', $transactionId)
            ->where('p.business_id', $biz)
            ->groupBy('pl.variation_id')
            ->selectRaw('pl.variation_id, SUM(pl.quantity) as qtd, SUM(pl.quantity * pl.purchase_price_inc_tax) as valor')
            ->get();

        $feito = [];
        foreach ($entradas as $e) {
            $qtd = (float) $e->qtd;
            if ($qtd <= 0) {
                continue;
            }
            $feito[] = DB::transaction(fn () => $this->recalcular($biz, (int) $e->variation_id, $transactionId, $qtd, (float) $e->valor));
        }

        return array_values(array_filter($feito));
    }

    /** @return array<string, mixed>|null */
    private function recalcular(int $biz, int $variationId, int $transactionId, float $qtd, float $valor): ?array
    {
        $ja = DB::table('compras_custo_medio_movimentos')
            ->where('business_id', $biz)->where('transaction_id', $transactionId)->where('variation_id', $variationId)
            ->exists();
        if ($ja) {
            return null;
        }

        $atual = DB::table('compras_custo_medio')
            ->where('business_id', $biz)->where('variation_id', $variationId)
            ->lockForUpdate()->first(['id', 'custo_medio']);

        $qtdAntes = max(0.0, $this->estoque($biz, $variationId) - $qtd);
        $custoAntes = $atual !== null ? (float) $atual->custo_medio : $this->semente($biz, $variationId, $transactionId);
        $custoEntrada = round($valor / $qtd, 4);

        $depois = ($qtdAntes > 0 && $custoAntes !== null)
            ? round(($custoAntes * $qtdAntes + $valor) / ($qtdAntes + $qtd), 4)
            : $custoEntrada;

        $agora = now();
        $this->gravar($biz, $variationId, $depois, $atual?->id);
        DB::table('compras_custo_medio_movimentos')->insert([
            'business_id' => $biz, 'variation_id' => $variationId, 'transaction_id' => $transactionId,
            'origem' => 'entrada', 'qtd_antes' => $qtdAntes, 'custo_antes' => $custoAntes,
            'qtd_entrada' => $qtd, 'custo_entrada' => $custoEntrada, 'custo_depois' => $depois,
            'registrado_em' => $agora, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        return [
            'variation_id' => $variationId, 'qtd_antes' => $qtdAntes, 'custo_antes' => $custoAntes,
            'qtd_entrada' => $qtd, 'custo_entrada' => $custoEntrada, 'custo_depois' => $depois,
        ];
    }

    /**
     * Custo do estoque que ainda resta nos lotes: Σ restante × custo / Σ restante, nas compras
     * recebidas, saldo inicial, produção e transferências da empresa. Restante = quantidade −
     * vendida − ajustada − devolvida − usada na fabricação. Nulo se não resta nada.
     */
    public function semente(int $biz, int $variationId, ?int $excetoTransaction = null): ?float
    {
        $restante = '(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned - pl.mfg_quantity_used)';
        $r = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $biz)
            ->where('pl.variation_id', $variationId)
            ->where(function ($q) {
                $q->where(fn ($p) => $p->where('t.type', 'purchase')->where('t.status', 'received'))
                    ->orWhereIn('t.type', ['opening_stock', 'production_purchase', 'purchase_transfer']);
            })
            ->when($excetoTransaction !== null, fn ($q) => $q->where('t.id', '!=', $excetoTransaction))
            ->whereRaw("$restante > 0")
            ->selectRaw("SUM($restante) as qtd, SUM($restante * pl.purchase_price_inc_tax) as valor")
            ->first();

        $qtd = (float) ($r->qtd ?? 0);

        return $qtd > 0 ? round((float) $r->valor / $qtd, 4) : null;
    }

    /** Estoque da variação somado nos locais da empresa. */
    public function estoque(int $biz, int $variationId): float
    {
        return (float) DB::table('variation_location_details as vld')
            ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
            ->where('bl.business_id', $biz)
            ->where('vld.variation_id', $variationId)
            ->sum('vld.qty_available');
    }

    /** Custo médio vigente num momento: o último recálculo até ele. Nulo se não havia nenhum. */
    public function noMomento(int $biz, int $variationId, string $momento): ?float
    {
        $c = DB::table('compras_custo_medio_movimentos')
            ->where('business_id', $biz)->where('variation_id', $variationId)
            ->where('registrado_em', '<=', $momento)
            ->orderByDesc('registrado_em')->orderByDesc('id')
            ->value('custo_depois');

        return $c !== null ? (float) $c : null;
    }

    /** Grava a semente de uma variação sem custo guardado (comando compras:custo-medio-semear). */
    public function semear(int $biz, int $variationId, float $custo): bool
    {
        return DB::transaction(function () use ($biz, $variationId, $custo) {
            $existe = DB::table('compras_custo_medio')->where('business_id', $biz)->where('variation_id', $variationId)->lockForUpdate()->exists();
            if ($existe) {
                return false;
            }
            $agora = now();
            $this->gravar($biz, $variationId, round($custo, 4), null);
            DB::table('compras_custo_medio_movimentos')->insert([
                'business_id' => $biz, 'variation_id' => $variationId, 'transaction_id' => null,
                'origem' => 'semente', 'qtd_antes' => $this->estoque($biz, $variationId), 'custo_antes' => null,
                'qtd_entrada' => 0, 'custo_entrada' => null, 'custo_depois' => round($custo, 4),
                'registrado_em' => $agora, 'created_at' => $agora, 'updated_at' => $agora,
            ]);

            return true;
        });
    }

    private function gravar(int $biz, int $variationId, float $custo, ?int $id): void
    {
        if ($id !== null) {
            DB::table('compras_custo_medio')->where('id', $id)->update(['custo_medio' => $custo, 'updated_at' => now()]);

            return;
        }
        DB::table('compras_custo_medio')->insert([
            'business_id' => $biz, 'variation_id' => $variationId, 'custo_medio' => $custo,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
