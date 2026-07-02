<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Handle imutável devolvido por EstoqueFixture — identifica um produto de teste e suas
 * variações (product_variation_id + variation_id por índice).
 *
 * @see EstoqueFixture
 */
final class EstoqueProduto
{
    /**
     * @param  array<int,array{variation_id:int,product_variation_id:int}>  $variations
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $productId,
        public readonly int $unitId,
        public readonly array $variations,
    ) {
    }

    public function variationId(int $index = 0): int
    {
        return $this->variations[$index]['variation_id'];
    }

    public function productVariationId(int $index = 0): int
    {
        return $this->variations[$index]['product_variation_id'];
    }
}
