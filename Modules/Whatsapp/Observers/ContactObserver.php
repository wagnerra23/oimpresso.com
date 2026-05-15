<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Observers;

use App\Contact;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;

/**
 * ContactObserver — invalida cache `whatsapp.auto_link:*` quando phone
 * fields de um Contact CRM mudam (mobile/landline/alternate_number).
 *
 * **Por que existe (incident 2026-05-15 16h BRT — smoke pós-Baileys 7.x):**
 *
 * Após deploy Baileys 7.x funcional, observamos cross-contact recorrente.
 * Eliana (contact_id=6005) tinha `alternate_number=48999872822` (= número
 * Wagner cadastrado errado no contato dela). Limpei o campo via SQL UPDATE
 * mas conversação NOVA (id=39) ainda nasceu vinculada à Eliana.
 *
 * **Root cause identificada:** `ConversationContactLinker::attemptLink()`
 * cacheia mapping LID→Contact por **1h TTL** ([linker:97-122](Services/Contacts/ConversationContactLinker.php)).
 * O cache foi populado às 09:27 (quando msg real chegou), Eliana ainda tinha
 * `alt=48999872822` que batia. Limpei alt às 09:30 — mas cache permaneceu
 * apontando pra Eliana até ~10:27 (TTL expiry). Cross-contact persistiu
 * via cache stale.
 *
 * **Fix:** este observer detecta mudanças em campos phone do Contact e
 * invalida proativamente as keys de cache relacionadas — antes era SÓ
 * `LidPhoneResolver::record()` que invalidava (linha 166), mas só pra
 * mapping LID→phone, não pra Contact-side editing.
 *
 * **Defesa-em-profundidade:** invalida TODOS os 3 fields (old + new value)
 * pra cobrir cenários edge:
 * - Mobile mudou: cache antigo do mobile antigo precisa morrer
 * - Mobile mudou: cache do mobile NOVO também invalida (re-evaluation)
 * - Alternate zerado: cache do valor antigo morre
 *
 * Tier 0 multi-tenant ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * `business_id` do Contact passado pro `forgetAttemptLinkCache()` — cache
 * isolado por biz.
 *
 * Log sem PII (LGPD): só ids numéricos + count fields changed, nunca phone
 * real.
 *
 * @see Modules\Whatsapp\Services\Contacts\ConversationContactLinker::forgetAttemptLinkCache
 * @see memory/sessions/2026-05-15-... (handoff cross-contact cache stale)
 */
class ContactObserver
{
    /**
     * Hook `saved` cobre create + update. Em `created`, Contact novo NÃO
     * deveria ter cache prévio — mas chamamos por defense-in-depth (no-op
     * se cache não existe).
     */
    public function saved(Contact $contact): void
    {
        $phoneFields = ['mobile', 'landline', 'alternate_number'];
        $changedFields = [];

        foreach ($phoneFields as $field) {
            if ($contact->wasChanged($field)) {
                $changedFields[] = $field;
            }
        }

        if (empty($changedFields)) {
            return; // Phone fields intactos — cache irrelevante
        }

        $linker = app(ConversationContactLinker::class);

        $invalidated = 0;
        foreach ($changedFields as $field) {
            $oldPhone = (string) $contact->getOriginal($field);
            $newPhone = (string) ($contact->{$field} ?? '');

            if ($oldPhone !== '') {
                $linker->forgetAttemptLinkCache($contact->business_id, $oldPhone);
                $invalidated++;
            }
            if ($newPhone !== '' && $newPhone !== $oldPhone) {
                $linker->forgetAttemptLinkCache($contact->business_id, $newPhone);
                $invalidated++;
            }
        }

        Log::info('[whatsapp.contact_observer.cache_invalidated]', [
            'contact_id' => $contact->id,
            'business_id' => $contact->business_id,
            'changed_fields' => $changedFields,
            'cache_keys_invalidated' => $invalidated,
        ]);
    }
}
