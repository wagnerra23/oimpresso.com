<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Summarizer;

use Illuminate\Support\Facades\App;

/**
 * Onda 5 — Agent A1 (Auto-summary docs longos).
 *
 * Helper estático invocado por tools MCP (`decisions-fetch`, `tasks-detail`,
 * `kb-answer`) antes de `Response::text()`. Roteia automaticamente:
 *   - response < threshold → passa direto (zero custo)
 *   - response >= threshold → AutoSummarizerService::summarize() (cache 24h)
 *
 * Output convenciona footer markdown com flags:
 *   - `_truncated: true` (transparência pro consumidor MCP)
 *   - `_full_response_id: <hash>` (link pra recuperar full via tool dedicada)
 *   - `_reason: <generated|cache_hit|cap_exceeded|llm_error>`
 *
 * Pattern conservador (princípio 8 Constituição v2 — Confiabilidade com
 * fallback): em qualquer falha LOCAL no helper (Service::summarize disparou
 * exception), retorna texto original truncado SEM crashar a tool MCP.
 *
 * @see memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §A1
 */
final class AutoSummarizerHelper
{
    /**
     * Summariza response longa se exceder threshold; senão passthrough.
     *
     * Retorna SummaryResult — caller monta footer markdown via
     * AutoSummarizerHelper::renderFooter($result).
     *
     * @param  string  $response  Texto bruto que a tool quer retornar
     * @param  int|null  $maxSize  Override threshold (default config 8000)
     */
    public static function summarizeIfLarge(string $response, ?int $maxSize = null): SummaryResult
    {
        $threshold = $maxSize
            ?? (int) config('copiloto.auto_summarizer.threshold_chars', 8000);

        // Fast path — resposta pequena
        if (mb_strlen($response) < $threshold) {
            return SummaryResult::passthrough($response, SummaryResult::REASON_BELOW_THRESHOLD);
        }

        // Resolve service (singleton) e summariza
        try {
            $service = App::make(AutoSummarizerService::class);

            return $service->summarize($response, ['threshold_chars' => $threshold]);
        } catch (\Throwable $e) {
            // Princípio 8 — fallback safe (nunca quebra tool MCP)
            return SummaryResult::passthrough(
                self::truncate($response, $threshold),
                SummaryResult::REASON_LLM_ERROR
            );
        }
    }

    /**
     * Renderiza footer markdown standardizado pro consumidor MCP saber que
     * houve summarization (princípio 7 — transparência).
     */
    public static function renderFooter(SummaryResult $result): string
    {
        if (! $result->truncated) {
            return '';
        }

        $lines = ["\n\n---\n\n_⚠️ Auto-summary aplicado:_"];
        $lines[] = "- `_truncated: true`";
        $lines[] = "- `_reason: {$result->reason}`";

        if ($result->hash !== null) {
            $lines[] = "- `_full_response_id: {$result->hash}` (cache MySQL, TTL 24h)";
        }

        if ($result->reason === SummaryResult::REASON_GENERATED) {
            $costBrl = number_format($result->costBrl, 6, '.', '');
            $lines[] = "- `_tokens: in={$result->tokensIn} / out={$result->tokensOut} / chunks={$result->chunks} / cost=R\${$costBrl}`";
        } elseif ($result->reason === SummaryResult::REASON_CAP_EXCEEDED) {
            $lines[] = '- `_note:` cap mensal `JANA_SUMMARIZER_MAX_COST_BRL` excedido — texto truncado, ver doc original';
        } elseif ($result->reason === SummaryResult::REASON_LLM_ERROR) {
            $lines[] = '- `_note:` LLM falhou — texto truncado, ver doc original';
        }

        return implode("\n", $lines);
    }

    /**
     * Combina summarize + render footer + retorna string final pronta pra
     * `Response::text()`. Conveniência pros tools MCP.
     */
    public static function summarizeAndRender(string $response, ?int $maxSize = null): string
    {
        $result = self::summarizeIfLarge($response, $maxSize);

        return $result->summary . self::renderFooter($result);
    }

    /**
     * Trunca preservando inicio + nota.
     */
    private static function truncate(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars - 80)
            . "\n\n_[... truncado: auto-summarizer indisponível, ver doc original]_";
    }
}
