<?php

declare(strict_types=1);

namespace App\Contracts\Fabricacao;

/**
 * Quais produtos entram como insumo em alguma receita da Fabricação, vistos pelo núcleo (UC-PIDX-44 · CV1:
 * o filtro "Uso" da consulta de produto chama de "Material" o item que entra em alguma receita).
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest). Quem
 * implementa é `Modules\Manufacturing\Services\ProdutosDeReceitaService` (`mfg_recipes` ×
 * `mfg_recipe_ingredients`), ligado pelo ManufacturingServiceProvider. Sem o módulo, a interface não está
 * ligada: quem consome confere `app()->bound(ProdutosDeReceita::class)` e não oferece "Material".
 */
interface ProdutosDeReceita
{
    /**
     * Ids dos produtos cuja variação aparece como insumo de alguma receita da empresa, em ordem crescente.
     * Tier 0: a receita e o insumo só do `$businessId`.
     *
     * @return list<int>
     */
    public function materiais(int $businessId): array;
}
