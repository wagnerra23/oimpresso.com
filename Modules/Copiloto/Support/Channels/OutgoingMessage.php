<?php

namespace Modules\Copiloto\Support\Channels;

/**
 * DTO de mensagem outbound (app → provider).
 *
 * Sempre carrega o tenant scope (`businessId`, `userId`) — guardrail anti-leak
 * multi-tenant: driver não envia sem business_id resolvido.
 */
final class OutgoingMessage
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $userId,
        public readonly string $wireId,        // destino (ex.: '+5511XXX...')
        public readonly string $text,
        public readonly ?string $replyToProviderMessageId = null,
    ) {
    }
}
