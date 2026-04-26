<?php

declare(strict_types=1);

namespace App\Services\Evolution\Embeddings;

/**
 * Driver determinístico baseado em hashing — funciona offline, sem API.
 *
 * Uso:
 * - testes (Pest): nunca chama API real, score determinístico → eval estável.
 * - dev local sem chave Voyage: roda evolution:index + evolution:query sem custo.
 *
 * Substituível em prod por VoyageEmbeddingDriver quando VOYAGEAI_API_KEY for setado.
 */
class HashEmbeddingDriver implements EmbeddingDriver
{
    public function __construct(private readonly int $dimensions = 256) {}

    public function embed(array $inputs): array
    {
        return array_map(fn (string $input): array => $this->hashEmbed($input), $inputs);
    }

    public function name(): string
    {
        return 'hash-local';
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * @return array<int, float>
     */
    private function hashEmbed(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);
        $tokens = $this->tokenize($text);

        if (empty($tokens)) {
            return $vector;
        }

        foreach ($tokens as $token) {
            $hash = crc32($token);
            $idx = $hash % $this->dimensions;
            $sign = ((crc32('s'.$token) & 1) === 0) ? 1.0 : -1.0;
            $vector[$idx] += $sign;
        }

        $norm = 0.0;
        foreach ($vector as $v) {
            $norm += $v * $v;
        }
        $norm = sqrt($norm);

        if ($norm === 0.0) {
            return $vector;
        }

        return array_map(fn ($v) => $v / $norm, $vector);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower($text);
        $tokens = preg_split('/[\s,;.!?\-_()\[\]{}\/\\\\:]+/u', $normalized) ?: [];

        return array_values(array_filter($tokens, fn ($t) => $t !== '' && mb_strlen($t) >= 2));
    }
}
