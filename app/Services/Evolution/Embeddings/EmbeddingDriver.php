<?php

declare(strict_types=1);

namespace App\Services\Evolution\Embeddings;

interface EmbeddingDriver
{
    /**
     * Embed N inputs at once. Returns ordered array<int, array<int, float>>.
     *
     * @param  array<int, string>  $inputs
     * @return array<int, array<int, float>>
     */
    public function embed(array $inputs): array;

    public function name(): string;

    public function dimensions(): int;
}
