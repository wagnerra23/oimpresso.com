<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use Modules\Whatsapp\Entities\Channel;

/**
 * Observabilidade D9.a (ADR 0155): factory mapping é constante-time;
 * Tracer via `OtelHelper::span(` reservado pra drivers concretos retornados.
 *
 * ChannelDriverFactory — resolve `DriverInterface` a partir de `Channel`.
 *
 * Decisão mãe: ADR 0135 (omnichannel inbox).
 *
 * Substitui long-term `DriverFactory::make()` que recebia
 * `WhatsappBusinessConfig|WhatsappBusinessPhone`. Mantemos o legacy intacto
 * em paralelo neste PR — drivers/jobs/webhooks Whatsapp continuam funcionando.
 *
 * Mapeamento `Channel::type` → driver class:
 *   whatsapp_meta     → MetaCloudDriver  (existe — default ADR 0202)
 *   whatsapp_zapi     → ZapiDriver       (existe — opcional fallback ADR 0202)
 *   whatsapp_baileys  → NotImplementedDriverException  (descontinuado ADR 0202)
 *   instagram         → not_implemented  (Fase 1 — gate cliente)
 *   messenger         → not_implemented  (Fase 1)
 *   email_imap        → not_implemented  (Fase 2)
 *   email_smtp        → not_implemented  (Fase 2)
 *   mercadolivre      → not_implemented  (Fase 3 — só com cliente pagante)
 *
 * Drivers Whatsapp consomem `WhatsappBusinessPhone` historicamente — quando
 * chamados a partir de `Channel`, este factory monta um Phone-shim em memória
 * com as creds extraídas de `Channel::config_json` (PR B refatora drivers
 * pra consumir Channel direto).
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class ChannelDriverFactory
{
    public static function resolve(Channel $channel): DriverInterface
    {
        $forbidden = config('whatsapp.forbidden_drivers', []);
        if (in_array($channel->type, $forbidden, true)) {
            throw new \InvalidArgumentException(
                "Channel type '{$channel->type}' está em forbidden_drivers."
            );
        }

        return match ($channel->type) {
            Channel::TYPE_WHATSAPP_META => app(MetaCloudDriver::class),
            Channel::TYPE_WHATSAPP_ZAPI => app(ZapiDriver::class),
            // ADR 0204 (2026-05-27) — whatsmeow substituto não-oficial Baileys.
            Channel::TYPE_WHATSAPP_WHATSMEOW => app(WhatsmeowDriver::class),
            Channel::TYPE_WHATSAPP_BAILEYS => throw new NotImplementedDriverException(
                "Channel type 'whatsapp_baileys' foi descontinuado por ADR 0202 (2026-05-27). "
                . "Substituído por 'whatsapp_whatsmeow' (ADR 0204) ou crie channel 'whatsapp_meta' (default). "
                . "Veja memory/decisions/0204-whatsmeow-driver-substituto-baileys.md."
            ),

            Channel::TYPE_INSTAGRAM,
            Channel::TYPE_MESSENGER,
            Channel::TYPE_EMAIL_IMAP,
            Channel::TYPE_EMAIL_SMTP,
            Channel::TYPE_MERCADOLIVRE => throw new NotImplementedDriverException(
                "Driver pra channel type '{$channel->type}' não implementado nesta fase. "
                . "Ver ADR 0135 — Fase 1 (Insta/Messenger), Fase 2 (Email), Fase 3 (ML)."
            ),

            default => throw new \InvalidArgumentException(
                "Channel type '{$channel->type}' desconhecido. Tipos válidos: "
                . implode(', ', Channel::TYPES)
            ),
        };
    }
}
