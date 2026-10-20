<?php

declare(strict_types=1);

namespace App\Contracts\Fabricacao;

/**
 * Insumos da receita (ficha técnica) de um produto, visto pelo núcleo (US-PCP-031 · UC-FLX-14).
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Manufacturing\Services\InsumosDaReceitaService` (a receita mora em
 * `mfg_recipes` / `mfg_recipe_ingredients`), ligado pelo ManufacturingServiceProvider. Sem o módulo,
 * a interface não está ligada: quem consome confere `app()->bound(InsumosDaReceita::class)` e segue
 * sem reserva.
 */
interface InsumosDaReceita
{
    /**
     * Quanto de cada insumo que controla estoque a receita do produto pede para produzir `$quantidade`
     * (na unidade base do produto). Por unidade base: quantidade do insumo × multiplicador da
     * sub-unidade dele ÷ (rendimento da receita × multiplicador da sub-unidade da receita) — a mesma
     * conta do Manufacturing (`ManufacturingUtil::getIngredientDetails`, `unit_quantity`).
     * Arredondado a 4 casas pelo MySQL (DECIMAL exato), nunca float.
     *
     * Vazio quando o produto não tem receita na empresa, a receita não tem rendimento ou nenhum
     * insumo controla estoque. Tier 0: receita e insumos só do `$businessId`.
     *
     * @return list<array{product_id:int, variation_id:int, quantidade:string}>
     */
    public function paraQuantidade(int $businessId, int $variationId, string $quantidade): array;
}
