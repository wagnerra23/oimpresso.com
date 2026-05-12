<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

/**
 * DriverDoesNotSupport — driver não suporta tipo de mensagem interativa solicitado.
 *
 * Lançada quando caller pede CTA URL pro Z-API (não suporta), list pro Baileys
 * em versão <6.7 (não suporta nativo), ou variantes que dependem de feature
 * exclusiva de um provider.
 *
 * Job consumidor (`SendInteractiveJob`) trata como falha permanente (não retry)
 * e marca `WhatsappMessage::status = 'failed'` com motivo legível.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-045, US-WA-046
 */
final class DriverDoesNotSupport extends \RuntimeException
{
    public function __construct(
        public readonly string $driverName,
        public readonly string $featureKey,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? "Driver '{$driverName}' não suporta '{$featureKey}'.",
        );
    }

    public static function for(string $driverName, string $featureKey): self
    {
        return new self($driverName, $featureKey);
    }
}
