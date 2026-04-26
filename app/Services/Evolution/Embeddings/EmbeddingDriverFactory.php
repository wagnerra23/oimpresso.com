<?php

declare(strict_types=1);

namespace App\Services\Evolution\Embeddings;

class EmbeddingDriverFactory
{
    public static function make(?string $provider = null): EmbeddingDriver
    {
        $provider = $provider ?? config('evolution.embedding_provider', 'voyageai');
        $voyageKey = (string) config('prism.providers.voyageai.api_key', '');

        if ($provider === 'voyageai' && $voyageKey !== '') {
            return new VoyageEmbeddingDriver(
                model: (string) config('evolution.embedding_model', 'voyage-3-lite'),
            );
        }

        return new HashEmbeddingDriver;
    }
}
