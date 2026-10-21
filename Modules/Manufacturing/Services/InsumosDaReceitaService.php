<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Services;

use App\Contracts\Fabricacao\InsumosDaReceita;
use Illuminate\Support\Facades\DB;

/**
 * Implementa `App\Contracts\Fabricacao\InsumosDaReceita` (US-PCP-031 · UC-FLX-14): a receita do
 * Manufacturing é a fonte do material de um produto feito sob encomenda (decisão [W] 2026-10-15,
 * `_DECISOES-W-2026-10-15.md`: "Fabricação (receitas) fica como fonte de material").
 *
 * Só LEITURA. Uma receita por variação (a de menor id, se houver mais de uma). Insumo repetido na
 * receita soma. Tier 0: a receita e cada insumo passam pelo `products.business_id` (o Manufacturing
 * legado não tem escopo global).
 */
final class InsumosDaReceitaService implements InsumosDaReceita
{
    public function paraQuantidade(int $businessId, int $variationId, string $quantidade): array
    {
        $receitaId = DB::table('mfg_recipes as r')
            ->join('variations as rv', 'rv.id', '=', 'r.variation_id')
            ->join('products as rp', 'rp.id', '=', 'rv.product_id')
            ->where('rp.business_id', $businessId)->where('r.variation_id', $variationId)
            ->where('r.total_quantity', '>', 0)
            ->min('r.id');
        if ($receitaId === null) {
            return [];
        }

        $linhas = DB::table('mfg_recipes as r')
            ->join('mfg_recipe_ingredients as i', 'i.mfg_recipe_id', '=', 'r.id')
            ->join('variations as v', 'v.id', '=', 'i.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('units as rsu', 'rsu.id', '=', 'r.sub_unit_id')
            ->leftJoin('units as isu', 'isu.id', '=', 'i.sub_unit_id')
            ->where('r.id', $receitaId)->where('p.business_id', $businessId)
            ->where('p.enable_stock', 1)->whereNull('v.deleted_at')
            ->groupBy('i.variation_id', 'v.product_id', 'r.total_quantity', 'rsu.base_unit_multiplier')
            ->orderBy('i.variation_id')
            ->selectRaw('i.variation_id, v.product_id, ROUND(SUM(i.quantity * COALESCE(isu.base_unit_multiplier, 1)) * ? / (r.total_quantity * COALESCE(rsu.base_unit_multiplier, 1)), 4) AS quantidade', [$quantidade])
            ->get();

        return $linhas->map(fn ($l) => [
            'product_id' => (int) $l->product_id,
            'variation_id' => (int) $l->variation_id,
            'quantidade' => $this->quatroCasas((string) $l->quantidade),
        ])->values()->all();
    }

    private function quatroCasas(string $valor): string
    {
        [$inteiro, $fracao] = array_pad(explode('.', trim($valor) === '' ? '0' : trim($valor), 2), 2, '');

        return ($inteiro === '' ? '0' : $inteiro).'.'.substr(str_pad($fracao, 4, '0'), 0, 4);
    }
}
