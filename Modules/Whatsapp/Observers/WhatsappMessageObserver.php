<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Observers;

use Modules\Whatsapp\Entities\WhatsappMessage;

/**
 * Append-only enforcement em WhatsappMessage (Tier 0 — ADR 0093 + ADR 0096).
 *
 * Bloqueia UPDATE em colunas que JAMAIS podem mudar após INSERT
 * (ver `WhatsappMessage::IMMUTABLE_COLUMNS`):
 * - business_id, conversation_id, direction, provider, provider_message_id,
 *   body, payload, sender_user_id, sender_kind
 *
 * Updates permitidos só em: status, failed_reason, updated_at, cost_centavos
 * (status delivery flow: queued → sent → delivered → read).
 *
 * **Exceção one-time set:** `provider_message_id` é preenchido pelo
 * `SendWhatsappMessageJob` APÓS o driver responder (Z-API/Meta retornam o ID
 * só na resposta). Permitido transição `null|''` → valor real UMA VEZ;
 * subsequente UPDATE pra outro valor é bloqueado (preserva append-only).
 *
 * Padrão Ponto Marcacoes (memory/proibicoes.md — append-only por força de lei).
 *
 * @see Modules/Whatsapp/Entities/WhatsappMessage::IMMUTABLE_COLUMNS
 * @see Modules/Whatsapp/Jobs/SendWhatsappMessageJob — preenche provider_message_id
 */
class WhatsappMessageObserver
{
    /**
     * Bloqueia UPDATE em colunas imutáveis.
     *
     * Lança DomainException se alguma coluna IMMUTABLE_COLUMNS está em
     * `getDirty()` E é diferente do valor original (`getOriginal()`).
     *
     * Exceção: `provider_message_id` aceita transição `null|''` → valor real
     * (one-time set após driver responder).
     */
    public function saving(WhatsappMessage $message): void
    {
        // Skip em INSERT (exists=false significa primeira gravação)
        if (! $message->exists) {
            return;
        }

        $dirty = $message->getDirty();
        $violations = [];

        foreach (WhatsappMessage::IMMUTABLE_COLUMNS as $col) {
            if (! array_key_exists($col, $dirty)) {
                continue;
            }
            $original = $message->getOriginal($col);

            // One-time set permitido em provider_message_id: null|'' → valor real
            // (driver responde com ID só após enviar — fluxo SendWhatsappMessageJob)
            if ($col === 'provider_message_id'
                && in_array($original, [null, ''], true)
                && ! in_array($dirty[$col], [null, ''], true)) {
                continue;
            }

            if ($dirty[$col] !== $original) {
                $violations[] = "{$col}: '{$original}' → '{$dirty[$col]}'";
            }
        }

        if (! empty($violations)) {
            throw new \DomainException(
                "WhatsappMessage append-only violation (id={$message->id}): "
                . implode('; ', $violations)
                . ". Colunas imutáveis (ver IMMUTABLE_COLUMNS). Use INSERT pra mudança."
            );
        }
    }

    /**
     * Bloqueia DELETE direto — preserva histórico fiscal/audit.
     */
    public function deleting(WhatsappMessage $message): void
    {
        throw new \DomainException(
            "WhatsappMessage hard-delete bloqueado (id={$message->id}). "
            . "Mensagens são append-only por compliance LGPD/audit. "
            . "Use anonimização via php artisan whatsapp:forget-contact se for direito-ao-esquecimento."
        );
    }
}
