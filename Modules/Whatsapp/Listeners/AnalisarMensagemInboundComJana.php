<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Jobs\AnalisarMensagemInboundJob;

/**
 * US-WA-095 — Listener síncrono que enfileira análise IA pra cada msg inbound.
 *
 * Filtra cedo (sem dispatch Job) pra evitar enfileirar:
 *   - canal/business sem flag `whatsapp.analise.enabled`
 *   - outbound (já filtrado pelo evento mas defesa em profundidade)
 *   - notas internas atendente
 *   - tipos não suportados (mídia entra após transcription Whisper)
 *   - body vazio
 *
 * Job real (`AnalisarMensagemInboundJob`) faz gates iguais — listener
 * só economiza dispatch. Skip aqui = zero custo de fila.
 *
 * Multi-tenant Tier 0: business_id vem do `$event->message` (Eloquent
 * com global scope aplicado).
 *
 * @see Modules/Whatsapp/Jobs/AnalisarMensagemInboundJob.php
 */
class AnalisarMensagemInboundComJana
{
    public function handle(OmnichannelMessageReceived $event): void
    {
        if (! (bool) config('whatsapp.analise.enabled', false)) {
            return;
        }

        $message = $event->message;

        if ($message->direction !== Message::DIRECTION_INBOUND) {
            return;
        }
        if ((bool) $message->is_internal_note) {
            return;
        }
        if (! in_array($message->type, ['text', 'interactive', 'template'], true)) {
            return;
        }
        if (trim((string) $message->body) === '') {
            return;
        }

        // Filtro per-business — Wagner pode habilitar análise só pra biz=1
        // antes de expandir (config 'whatsapp.analise.enabled_business_ids').
        $allowedBiz = config('whatsapp.analise.enabled_business_ids', []);
        if (is_array($allowedBiz) && count($allowedBiz) > 0
            && ! in_array($message->business_id, $allowedBiz, true)) {
            return;
        }

        // Filtro per-canal — bot_handling=true significa Jana bot ativo
        // E também queremos analisar pra dashboard voz-cliente. Sem flag
        // específica per-channel ainda (config global suficiente Sprint 1).

        AnalisarMensagemInboundJob::dispatch($message->business_id, $message->id);
    }
}
