<?php

namespace Modules\Whatsapp\Services\Drivers;

/**
 * Status de uma mensagem (resultado de fetchMessageStatus).
 *
 * Observabilidade D9.a (ADR 0155): value-object puro — driver chamador
 * usa Tracer via `OtelHelper::span(` em fetchMessageStatus.
 */
final class MessageStatus
{
    public function __construct(
        public readonly string $status, // queued|sent|delivered|read|failed
        public readonly ?string $failedReason = null,
        public readonly ?\DateTimeInterface $deliveredAt = null,
        public readonly ?\DateTimeInterface $readAt = null,
    ) {}
}
