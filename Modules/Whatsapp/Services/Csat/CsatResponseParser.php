<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Csat;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\CsatResponse;

/**
 * Observabilidade D9.a (ADR 0155): parse regex sub-µs; Tracer pai
 * via `OtelHelper::span(` herda o webhook span (CSAT inline com inbound).
 *
 * CsatResponseParser — extrai score 1-5 + comment de texto inbound (PR-6 CYCLE-07).
 *
 * Chamado pelo webhook (ChannelBaileysWebhookController::handleMessage) APÓS
 * `firstOrCreate` da Message inbound, SE há `CsatResponse` pending pra a
 * conversation (score=null, asked_at < 24h atrás).
 *
 * Regex tolerante:
 *   - "5"             → 5
 *   - " 5 "           → 5
 *   - "5/5"           → 5
 *   - "nota 5"        → 5
 *   - "Avalio 4!"     → 4
 *   - "⭐⭐⭐⭐⭐"        → 5 (conta U+2B50)
 *   - "★★★★"          → 4 (conta U+2605)
 *   - "5 obrigado!"   → score=5, comment="obrigado!"
 *   - "obrigado"      → null (sem score numérico/estrela)
 *   - "10"            → null (fora 1-5)
 *   - "0"             → null (fora 1-5)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL — `recordResponse` exige `businessId` explícito
 * (webhook context sem session); usa `withoutGlobalScopes()` com filter manual.
 *
 * @see CsatDispatcher
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap #5 P1
 */
class CsatResponseParser
{
    /**
     * Tenta extrair score 1-5 do corpo de uma mensagem inbound.
     *
     * @return ?int 1..5 se parseou; null se não há nota válida
     */
    public function tryParse(string $body): ?int
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        // 1. Conta estrelas Unicode (⭐ U+2B50, ★ U+2605, ✩ U+2729, ☆ U+2606)
        //    Mapeia 1-5 estrelas pro mesmo intervalo de score.
        $starsCount = preg_match_all('/[\x{2B50}\x{2605}\x{2729}\x{2606}]/u', $body);
        if ($starsCount >= 1 && $starsCount <= 5) {
            return $starsCount;
        }

        // 2. Procura primeiro número 1-5 isolado (não embebido em "10", "15" etc).
        //    Regex: lookbehind/lookahead pra não-dígito (ou borda).
        //    Aceita "5", " 5 ", "nota 5", "5/5", "5!", "5.", "Avalio 4!".
        //    Rejeita "10", "55", "150".
        if (preg_match('/(?<![0-9])([1-5])(?![0-9])/', $body, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Extrai comment (cauda) — texto após o primeiro número 1-5, se houver.
     *
     * Ex: "5 obrigado pelo atendimento!" → "obrigado pelo atendimento!"
     *     "Nota 4, gostei muito"          → "gostei muito"
     *     "5"                              → null (sem cauda)
     *     "⭐⭐⭐⭐⭐ valeu"                  → "valeu"
     *
     * @return ?string Comment ou null
     */
    public function tryParseComment(string $body): ?string
    {
        $body = trim($body);

        // Caso estrelas: remove todas estrelas + espaços ao redor + retorna cauda
        if (preg_match('/[\x{2B50}\x{2605}\x{2729}\x{2606}]+/u', $body)) {
            $rest = preg_replace('/[\x{2B50}\x{2605}\x{2729}\x{2606}\s\/\\\\\-]+/u', ' ', $body);
            $rest = trim((string) $rest);
            return $rest !== '' ? $rest : null;
        }

        // Caso numérico — remove tudo até o primeiro 1-5 isolado + pontuação
        // imediata ("5,", "5.", "5!", "5/5"), retorna o resto.
        if (preg_match('/(?<![0-9])[1-5](?![0-9])\s*[\/\\\\.,!\-]?\s*[\/\\\\.,!]?\s*([1-5]?)?\s*(.+)?$/u', $body, $m)) {
            $tail = trim((string) ($m[2] ?? ''));
            // Limpa pontuações iniciais residuais
            $tail = ltrim($tail, " \t\n\r\0\x0B.,!?-/\\");
            return $tail !== '' ? $tail : null;
        }

        return null;
    }

    /**
     * Atualiza row CsatResponse pending mais recente da conversation com
     * score + comment + response_message_id + responded_at.
     *
     * Se não há row pending dentro de 24h da `asked_at` → no-op + log info.
     *
     * Multi-tenant Tier 0 — filter explícito por business_id (caller webhook
     * passa o businessId do channel; não usa session).
     *
     * @return ?CsatResponse Row atualizada, ou null se não havia pending
     */
    public function recordResponse(
        int $businessId,
        int $conversationId,
        int $score,
        ?string $comment,
        int $messageId
    ): ?CsatResponse {
        if ($score < CsatResponse::SCORE_MIN || $score > CsatResponse::SCORE_MAX) {
            Log::warning('[csat.record.invalid_score]', [
                'business_id' => $businessId,
                'conversation_id' => $conversationId,
                'score' => $score,
            ]);
            return null;
        }

        // Busca a row pending mais recente dentro da janela 24h.
        $row = CsatResponse::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('conversation_id', $conversationId)
            ->whereNull('score')
            ->where('asked_at', '>=', now()->subHours(CsatResponse::DISPATCH_DEDUP_HOURS))
            ->orderByDesc('asked_at')
            ->first();

        if (! $row) {
            return null;
        }

        $row->forceFill([
            'score' => $score,
            'comment' => $comment ?: null,
            'response_message_id' => $messageId,
            'responded_at' => now(),
        ])->save();

        Log::info('[csat.record.recorded]', [
            'business_id' => $businessId,
            'conversation_id' => $conversationId,
            'csat_id' => $row->id,
            'score' => $score,
            'has_comment' => $comment !== null && $comment !== '',
        ]);

        return $row;
    }
}
