<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;

/**
 * NullDriver — implementação no-op para dev local + Pest CI.
 *
 * Não estoura rede. Gera provider_message_id UUID. Retorna sempre sucesso.
 *
 * Padrão canon (ADR 0050 Copiloto MeilisearchDriver/NullDriver).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002
 */
class NullDriver implements DriverInterface
{
    public function sendTemplate(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $templateName,
        array $params,
        string $locale = 'pt_BR',
    ): WhatsappSendResult {
        return WhatsappSendResult::ok('null-template-' . Str::uuid()->toString());
    }

    public function sendFreeform(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
    ): WhatsappSendResult {
        return WhatsappSendResult::ok('null-freeform-' . Str::uuid()->toString());
    }

    public function sendMedia(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $mediaUrl,
        string $type,
        ?string $caption = null,
    ): WhatsappSendResult {
        return WhatsappSendResult::ok('null-media-' . Str::uuid()->toString());
    }

    public function sendInteractive(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
        array $interactive,
    ): WhatsappSendResult {
        return WhatsappSendResult::ok('null-interactive-' . Str::uuid()->toString());
    }

    public function fetchMessageStatus(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $providerMessageId,
    ): MessageStatus {
        return new MessageStatus(status: 'delivered', deliveredAt: new \DateTimeImmutable());
    }

    public function ping(WhatsappBusinessConfig|WhatsappBusinessPhone $config): DriverHealthStatus
    {
        return DriverHealthStatus::healthy(displayPhone: '+5500000000000', sessionState: 'connected');
    }
}
