<?php

declare(strict_types=1);

namespace App\Contracts\Compras;

/**
 * Custo médio móvel por variação (US-PROD-024), visto pelo núcleo.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Compras\Services\CustoMedioMovel`, ligado pelo ComprasServiceProvider.
 * Sem o módulo, a interface não está ligada: quem consome confere `app()->bound(CustoMedio::class)`.
 */
interface CustoMedio
{
    /** A flag `compras.custo_medio_movel` da instalação. Desligada: ninguém mostra custo médio. */
    public function ligado(): bool;

    /** O custo médio vigente num momento (imposto incluso, por unidade base), ou nulo se não havia. */
    public function noMomento(int $biz, int $variationId, string $momento): ?float;
}
