<?php

namespace Modules\Whatsapp\Services\Drivers;

/**
 * Status de uma mensagem (resultado de fetchMessageStatus).
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
