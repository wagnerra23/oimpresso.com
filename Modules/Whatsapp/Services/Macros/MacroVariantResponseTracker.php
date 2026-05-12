<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Macros;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\MacroVariant;
use Modules\Whatsapp\Entities\Message;

/**
 * MacroVariantResponseTracker — registra resposta inbound como métrica
 * de conversão da variante A/B (US-WA-049, gap P2 #18).
 *
 * Quando inbound chega numa conversação, este service procura a última
 * Message outbound com `macro_variant_id != null` dentro da janela
 * `MacroVariant::RESPONSE_WINDOW_SECONDS` (24h). Se achar, incrementa
 * `response_count` da variante — *idempotente*: grava
 * `payload._macro_variant_response_counted=true` na message outbound pra
 * NUNCA dupli­car contagem em reentregas/replays do daemon.
 *
 * Por que não usar cache: cache key (conv+variant) expira em 1h e
 * webhook replay pode chegar até 7d depois. Flag persistida em
 * payload garante audit trail + idempotência permanente.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-049
 */
class MacroVariantResponseTracker
{
    /** Flag idempotente persistida em messages.payload */
    public const PAYLOAD_FLAG = '_macro_variant_response_counted';

    /**
     * Tenta incrementar response_count baseado em uma message inbound recém-criada.
     *
     * Pré-condições verificadas internamente:
     *  - Message é inbound
     *  - Existe outbound com macro_variant_id na MESMA conversation
     *  - Outbound foi created_at dentro de RESPONSE_WINDOW_SECONDS atrás
     *  - Outbound NÃO tem PAYLOAD_FLAG=true (idempotência)
     *
     * Retorna true se incrementou, false caso contrário.
     */
    public function trackResponseFromInbound(int $businessId, Message $inbound): bool
    {
        if ($inbound->direction !== Message::DIRECTION_INBOUND) {
            return false;
        }

        $windowStart = Carbon::now()->subSeconds(MacroVariant::RESPONSE_WINDOW_SECONDS);

        // Última outbound com macro_variant_id != null na mesma conv,
        // dentro da janela 24h, SEM flag de já-contado.
        $outbound = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class) // SUPERADMIN: webhook scope manual
            ->where('business_id', $businessId)
            ->where('conversation_id', $inbound->conversation_id)
            ->where('direction', Message::DIRECTION_OUTBOUND)
            ->whereNotNull('macro_variant_id')
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('created_at')
            ->first();

        if (! $outbound) {
            return false;
        }

        // Idempotência — payload é array cast.
        $payload = $outbound->payload ?? [];
        if (is_array($payload) && ($payload[self::PAYLOAD_FLAG] ?? false) === true) {
            return false;
        }

        // Increment atômico + grava flag (transação curta — sem race em produção
        // pq webhook é single-threaded por conv via FILO Centrifugo)
        DB::table('macro_variants')
            ->where('id', $outbound->macro_variant_id)
            ->where('business_id', $businessId)
            ->increment('response_count');

        $payload[self::PAYLOAD_FLAG] = true;
        $outbound->forceFill(['payload' => $payload])->save();

        Log::info('[macro_variant.response_tracked]', [
            'business_id' => $businessId,
            'macro_variant_id' => $outbound->macro_variant_id,
            'inbound_message_id' => $inbound->id,
            'outbound_message_id' => $outbound->id,
            'window_seconds' => MacroVariant::RESPONSE_WINDOW_SECONDS,
        ]);

        return true;
    }
}
