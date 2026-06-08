<?php

namespace Modules\Whatsapp\Services\Drivers;

/**
 * Resultado padronizado de envio de mensagem (qualquer driver).
 *
 * Observabilidade: value-object puro; spans OTel ficam no driver chamador
 * via `OtelHelper::span(` ou `OtelHelper::spanBiz(` (Tracer canônico, ADR 0155 D9.a).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002
 */
final class WhatsappSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $sessionLost = false,
        public readonly bool $banDetected = false,
    ) {}

    public static function ok(string $providerMessageId): self
    {
        return new self(success: true, providerMessageId: $providerMessageId);
    }

    public static function failed(string $errorCode, string $errorMessage, bool $sessionLost = false, bool $banDetected = false): self
    {
        return new self(
            success: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            sessionLost: $sessionLost,
            banDetected: $banDetected,
        );
    }
}
