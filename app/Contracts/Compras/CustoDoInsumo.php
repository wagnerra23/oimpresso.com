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

    /** O método da regra "Atualizar o custo dos insumos" da empresa: `medio`, `ultimo` ou `por_insumo`. */
    public function metodoDaEmpresa(int $businessId): string;

    /** O método próprio do insumo (`medio` | `ultimo`), ou nulo se o insumo não tem um. */
    public function metodoDoInsumo(int $businessId, int $variationId): ?string;
}
