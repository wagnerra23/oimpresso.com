<?php

declare(strict_types=1);

namespace App\Contracts\Compras;

use Carbon\CarbonInterface;

/**
 * Custo do insumo numa data (US-COM-024 · UC-FLX-38), visto pelo núcleo.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Compras\Services\CustoDoInsumoService`, ligado pelo
 * ComprasServiceProvider. Sem o módulo, a interface não está ligada: quem consome confere
 * `app()->bound(CustoDoInsumo::class)` e segue sem custo.
 */
interface CustoDoInsumo
{
    /**
     * @return array{custo: ?string, metodo: string, data: string, compras: int, ultima_compra_em: ?string, motivo: ?string}
     */
    public function naData(int $businessId, int $variationId, CarbonInterface|string $data, ?string $metodo = null): array;
}
