<?php

declare(strict_types=1);

namespace App\Contracts\Fabricacao;

/**
 * A receita da Fabricação de um produto, vista pelo painel da consulta de produto (UC-PIDX-48 · D-29:
 * resumo só leitura, com "Abrir receita"; a edição continua na Fabricação).
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest). Quem
 * implementa é `Modules\Manufacturing\Services\RecipeBomService`, ligado pelo ManufacturingServiceProvider.
 * Sem o módulo a interface não está ligada: quem consome confere `app()->bound(ReceitaDoProduto::class)`.
 */
interface ReceitaDoProduto
{
    /**
     * As fichas das receitas do produto (uma por variação que tenha receita), com a MESMA conta da tela de
     * Receitas: insumos, quantidades, custo total, custo unitário e margem. Tier 0: só do `$businessId`.
     *
     * @return list<array<string, mixed>>
     */
    public function fichasDoProduto(int $productId, int $businessId): array;
}
