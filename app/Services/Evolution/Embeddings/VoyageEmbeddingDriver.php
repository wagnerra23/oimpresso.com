<?php

declare(strict_types=1);

namespace App\Services\Evolution\Embeddings;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

/**
 * Voyage-3-lite via Prism PHP. ~30% melhor recall em PT-BR vs OpenAI ada.
 *
 * @see memory/requisitos/EvolutionAgent/adr/tech/0001-prism-php-claude-padrao.md
 */
class VoyageEmbeddingDriver implements EmbeddingDriver
{
    public function __construct(
        private readonly string $model = 'voyage-3-lite',
        private readonly int $dimensions = 512,
    ) {}

    public function embed(array $inputs): array
    {
        if (empty($inputs)) {
            return [];
        }

        $response = Prism::embeddings()
            ->using(Provider::VoyageAI, $this->model)
            ->fromArray($inputs)
            ->asEmbeddings();

        $vectors = [];
        foreach ($response->embeddings as $embedding) {
            $vectors[] = $embedding->embedding;
        }

        return $vectors;
    }

    public function name(): string
    {
        return 'voyageai:'.$this->model;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
