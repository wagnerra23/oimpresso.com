<?php

declare(strict_types=1);

namespace Modules\KB\Services\Dtos;

/**
 * SummaryResult — saída canônica do KbRagService::summarize($slug).
 *
 * Resumo TL;DR de um node (artigo, ADR, session, runbook, etc.) gerado on-click
 * pelo botão "Resumir" da tri-pane do KB.
 *
 * @see memory/requisitos/KB/SCHEMA-DB-V1.md §11 POST /kb/ai/summarize/{slug}
 */
final class SummaryResult
{
    /**
     * @param  string                $tldr             1-2 frases — síntese executiva
     * @param  array<int,string>     $bulletPoints     3-6 bullets — pontos-chave
     * @param  string|null           $audienceHint     "Wagner governança" | "Larissa primeiro mês" | null
     * @param  int                   $sourceNodeId     ID do node resumido (kb_nodes.id)
     * @param  string                $sourceSlug       Slug pra deep-link UI
     * @param  string                $sourceType       article|adr|session|runbook|...
     * @param  int                   $latencyMs
     * @param  int                   $tokensIn
     * @param  int                   $tokensOut
     * @param  float                 $costEstimatedBrl
     * @param  bool                  $cacheHit
     */
    public function __construct(
        public readonly string $tldr,
        public readonly array $bulletPoints,
        public readonly ?string $audienceHint,
        public readonly int $sourceNodeId,
        public readonly string $sourceSlug,
        public readonly string $sourceType,
        public readonly int $latencyMs,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly float $costEstimatedBrl,
        public readonly bool $cacheHit = false,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'tldr'           => $this->tldr,
            'bullet_points'  => $this->bulletPoints,
            'audience_hint'  => $this->audienceHint,
            'source'         => [
                'kb_node_id' => $this->sourceNodeId,
                'slug'       => $this->sourceSlug,
                'type'       => $this->sourceType,
            ],
            'meta' => [
                'latency_ms'         => $this->latencyMs,
                'tokens_in'          => $this->tokensIn,
                'tokens_out'         => $this->tokensOut,
                'cost_estimated_brl' => $this->costEstimatedBrl,
                'cache_hit'          => $this->cacheHit,
            ],
        ];
    }
}
