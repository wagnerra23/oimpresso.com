<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Summarizer;

/**
 * Onda 5 — Agent A1 (Auto-summary docs longos).
 *
 * DTO imutável retornado por AutoSummarizerService::summarize() e
 * AutoSummarizerHelper::summarizeIfLarge(). Expõe pro caller:
 *   - $summary   — texto final (resumo, original ou truncado)
 *   - $truncated — true se response foi alterada (resumo OU truncagem)
 *   - $reason    — pq retornou: "cache_hit" | "generated" |
 *                  "below_threshold" | "cap_exceeded" | "llm_error"
 *   - $hash      — content_hash MD5 (link pro full via cache MySQL)
 *   - $cost_brl  — custo dessa call (0 em cache_hit/passthrough)
 *
 * Tools MCP usam isso pra adicionar `_truncated: true` + `_full_response_id`
 * (=hash) na response markdown — Wagner pode buscar versão completa
 * via tool dedicada se quiser depois.
 */
final class SummaryResult
{
    public const REASON_CACHE_HIT = 'cache_hit';
    public const REASON_GENERATED = 'generated';
    public const REASON_BELOW_THRESHOLD = 'below_threshold';
    public const REASON_CAP_EXCEEDED = 'cap_exceeded';
    public const REASON_LLM_ERROR = 'llm_error';

    public function __construct(
        public readonly string $summary,
        public readonly bool $truncated,
        public readonly string $reason,
        public readonly ?string $hash = null,
        public readonly int $tokensIn = 0,
        public readonly int $tokensOut = 0,
        public readonly float $costBrl = 0.0,
        public readonly int $chunks = 0,
    ) {}

    /**
     * Doc passou direto (threshold, cap excedido, ou LLM falhou).
     * $truncated reflete se houve modificação (cap/error truncou) ou passthrough puro.
     */
    public static function passthrough(string $text, string $reason): self
    {
        $truncated = in_array($reason, [self::REASON_CAP_EXCEEDED, self::REASON_LLM_ERROR], true);

        return new self(
            summary: $text,
            truncated: $truncated,
            reason: $reason,
        );
    }

    /**
     * Summary veio do cache MySQL (zero custo LLM).
     */
    public static function cacheHit(string $summary, string $hash): self
    {
        return new self(
            summary: $summary,
            truncated: true,
            reason: self::REASON_CACHE_HIT,
            hash: $hash,
        );
    }

    /**
     * Summary recém-gerado via map-reduce LLM.
     */
    public static function generated(
        string $summary,
        string $hash,
        int $tokensIn,
        int $tokensOut,
        float $costBrl,
        int $chunks,
    ): self {
        return new self(
            summary: $summary,
            truncated: true,
            reason: self::REASON_GENERATED,
            hash: $hash,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costBrl: $costBrl,
            chunks: $chunks,
        );
    }
}
