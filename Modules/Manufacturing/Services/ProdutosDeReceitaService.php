<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Services;

use App\Contracts\Fabricacao\ProdutosDeReceita;
use Illuminate\Support\Facades\DB;

/**
 * Implementa `App\Contracts\Fabricacao\ProdutosDeReceita` (UC-PIDX-44 · CV1): o produto que entra como
 * insumo em alguma receita é "Material" no filtro "Uso" da consulta de produto.
 *
 * Só LEITURA. Tier 0: a receita e o insumo passam pelo `products.business_id` (o Manufacturing legado não
 * tem escopo global nem `business_id` nas tabelas de receita), como em `InsumosDaReceitaService`.
 */
final class ProdutosDeReceitaService implements ProdutosDeReceita
{
    public function materiais(int $businessId): array
    {
        return DB::table('mfg_recipe_ingredients as i')
            ->join('mfg_recipes as r', 'r.id', '=', 'i.mfg_recipe_id')
            ->join('variations as rv', 'rv.id', '=', 'r.variation_id')
            ->join('products as rp', 'rp.id', '=', 'rv.product_id')
            ->join('variations as v', 'v.id', '=', 'i.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('rp.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->whereNull('v.deleted_at')
            ->distinct()
            ->orderBy('p.id')
            ->pluck('p.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
