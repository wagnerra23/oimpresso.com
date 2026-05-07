<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Whatsapp\Entities\WhatsappMessage;

/**
 * Disparado quando Driver retornou erro permanente (4xx).
 *
 * Listeners: LogConversation, AlertAdmin (Sentry-like), `WhatsappDriverHealthCheck`
 * recebe sinal de falha (Sprint 2 — Lote 2d). Se `banDetected=true`, marca
 * `driver_health=banned` e dispara fallback automático.
 *
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §5
 */
class WhatsappMessageFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WhatsappMessage $message,
        public readonly string $errorCode,
        public readonly string $errorMessage,
        public readonly bool $sessionLost = false,
        public readonly bool $banDetected = false,
    ) {
    }
}
