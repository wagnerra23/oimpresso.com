<?php

declare(strict_types=1);

namespace Modules\KB\Services\Dtos;

/**
 * MetaSuggestion — saída canônica do KbRagService::suggestMeta($bodyBlocks).
 *
 * Auto-tag de um artigo em rascunho: extrai title, excerpt e tags
 * sugeridas a partir do corpo (body_blocks) do composer.
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §11 POST /kb/ai/suggest-meta
 */
final class MetaSuggestion
{
    /**
     * @param  string             $title             Título sugerido (≤120 chars)
     * @param  string             $excerpt           Excerpt sugerido (≤300 chars)
     * @param  array<int,string>  $tags              Tags sugeridas (3-8 strings)
     * @param  string|null        $categorySlug      Categoria sugerida (kb_categories.slug) — null se incerto
     * @param  string|null        $nivel             iniciante|intermediario|avancado — null se não-operacional
     * @param  int                $latencyMs
     * @param  int                $tokensIn
     * @param  int                $tokensOut
     * @param  float              $costEstimatedBrl
     */
    public function __construct(
        public readonly string $title,
        public readonly string $excerpt,
        public readonly array $tags,
        public readonly ?string $categorySlug,
        public readonly ?string $nivel,
        public readonly int $latencyMs,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly float $costEstimatedBrl,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'title'         => $this->title,
            'excerpt'       => $this->excerpt,
            'tags'          => $this->tags,
            'category_slug' => $this->categorySlug,
            'nivel'         => $this->nivel,
            'meta' => [
                'latency_ms'         => $this->latencyMs,
                'tokens_in'          => $this->tokensIn,
                'tokens_out'         => $this->tokensOut,
                'cost_estimated_brl' => $this->costEstimatedBrl,
            ],
        ];
    }
}
