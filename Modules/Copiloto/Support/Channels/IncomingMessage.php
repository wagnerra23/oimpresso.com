<?php

namespace Modules\Copiloto\Support\Channels;

/**
 * DTO de mensagem inbound (provider → app).
 *
 * Channel-agnostic: parser do provider preenche; downstream consome
 * sem saber se veio de Evolution, Z-API, Meta Cloud ou web.
 */
final class IncomingMessage
{
    public function __construct(
        public readonly string $channel,        // 'evolution' | 'zapi' | 'meta' | 'web'
        public readonly string $providerMessageId,
        public readonly string $wireId,         // ex.: '+5511XXX...' (WhatsApp), 'web:123' (web)
        public readonly string $text,
        public readonly ?string $mediaUrl = null,
        public readonly ?string $mediaType = null, // 'image' | 'audio' | 'document'
        public readonly ?\DateTimeImmutable $sentAt = null,
        public readonly array $raw = [],        // payload bruto pra debug
    ) {
    }
}
