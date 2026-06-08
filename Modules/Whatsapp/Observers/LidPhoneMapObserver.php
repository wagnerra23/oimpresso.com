<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\LidPhoneMap;
use Modules\Whatsapp\Jobs\BackfillLidConversationsJob;

/**
 * LidPhoneMapObserver — dispara backfill quando LID resolve pra phone.
 *
 * Cenário (sessão 2026-05-14/15 — bug cross-contact Wagner-Eliana, PR2 do
 * wave-protocol-stack):
 *
 *   1. Cliente manda 1ª msg via @lid (sem `senderPn` no payload Baileys).
 *      `MessagePersister` cria Conversation com `customer_external_id` em
 *      formato `<lid>@lid` e `LidPhoneMap` row com `phone_e164 = NULL`
 *      (cache miss). `ConversationContactLinker::tryLink()` falha porque
 *      não tem phone normalizado pra cruzar com Contact CRM. Conversation
 *      órfã com `contact_id = NULL`.
 *
 *   2. Cliente manda 2ª msg, agora COM `senderPn` no payload. Webhook
 *      atualiza `LidPhoneMap.phone_e164` de NULL → `+5548...`. Sem este
 *      observer, conversation criada no passo 1 fica órfã PRA SEMPRE
 *      (`ConversationContactLinker` só roda no momento do persist da msg
 *      atual — não revisita conversations antigas).
 *
 *   3. Este observer detecta a transição NULL→valor (ou X→Y) em
 *      `phone_e164` e dispara `BackfillLidConversationsJob` que pega
 *      TODAS conversations do mesmo `business_id + lid` com
 *      `contact_id = NULL` e re-roda o `ConversationContactLinker`.
 *      Loop fechado.
 *
 * Trigger gate (evita re-fire em todo save):
 *   - Só dispara quando `phone_e164` mudou (Eloquent `wasChanged`).
 *   - Ignora bump `last_seen_at` puro (caso comum — webhook re-vê LID
 *     já conhecido).
 *
 * Log sem PII (ADR 0093 §LGPD): só `business_id` + `lid_prefix` (6 chars).
 * Phone nunca em log.
 *
 * @see \Modules\Whatsapp\Jobs\BackfillLidConversationsJob
 * @see \Modules\Whatsapp\Services\Contacts\ConversationContactLinker
 * @see memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md §6
 */
class LidPhoneMapObserver
{
    /**
     * Hook saved (post-update e post-insert) — detecta descoberta/troca
     * de phone e dispara backfill.
     */
    public function saved(LidPhoneMap $map): void
    {
        // Gate 1 — phone_e164 não mudou? bump last_seen_at puro, nada a fazer.
        if (! $map->wasChanged('phone_e164')) {
            return;
        }

        // Gate 2 — mudou pra NULL (perda de phone)? sem trigger; manteria
        // estado degradado. Backfill só faz sentido pra descoberta de valor.
        if ($map->phone_e164 === null) {
            return;
        }

        // Gate 3 — defesa extra contra wasChanged falso-positivo. Se valor
        // anterior == valor atual (não deveria, mas guarda contra cast quirks),
        // nada a fazer.
        $previousPhone = $map->getOriginal('phone_e164');
        if ($previousPhone === $map->phone_e164) {
            return;
        }

        Log::info('[whatsapp.lid_map.phone_discovered]', [
            'business_id' => $map->business_id,
            'lid_prefix' => substr((string) $map->lid, 0, 6) . '...',
            'had_previous' => $previousPhone !== null,
        ]);

        BackfillLidConversationsJob::dispatch(
            (int) $map->business_id,
            (string) $map->lid,
            (string) $map->phone_e164,
        )->onQueue((string) config('whatsapp.queue', 'whatsapp'));
    }
}
