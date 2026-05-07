<?php

namespace Modules\Whatsapp\Services\Drivers;

use Illuminate\Support\Str;

/**
 * NullDriver — implementação no-op para dev local + Pest CI.
 *
 * Não estoura rede. Gera provider_message_id UUID. Retorna sempre sucesso
 * (exceto quando explicitamente forçado via config('whatsapp.null_driver.fail_next')).
 *
 * Padrão canon (ADR 0050 Copiloto MeilisearchDriver/NullDriver).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002
 */
class NullDriver implements DriverInterface
{
    public function sendTemplate(array $config, string $to, string $templateName, array $params, string $locale = 'pt_BR'): WhatsappSendResult
    {
        return $this->fakeSuccess('null-template-' . Str::uuid()->toString());
    }

    public function sendFreeform(array $config, string $to, string $body): WhatsappSendResult
    {
        return $this->fakeSuccess('null-freeform-' . Str::uuid()->toString());
    }

    public function sendMedia(array $config, string $to, string $mediaUrl, string $type, ?string $caption = null): WhatsappSendResult
    {
        return $this->fakeSuccess('null-media-' . Str::uuid()->toString());
    }

    public function fetchMessageStatus(array $config, string $providerMessageId): MessageStatus
    {
        return new MessageStatus(status: 'delivered', deliveredAt: new \DateTimeImmutable());
    }

    public function ping(array $config): DriverHealthStatus
    {
        return DriverHealthStatus::healthy(displayPhone: '+5500000000000', sessionState: 'connected');
    }

    private function fakeSuccess(string $providerMessageId): WhatsappSendResult
    {
        return WhatsappSendResult::ok($providerMessageId);
    }
}
