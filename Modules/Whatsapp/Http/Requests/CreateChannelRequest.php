<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Requests;

/**
 * CreateChannelRequest — alias semântico de ChannelRequest pra criação.
 *
 * D8.c Security: padroniza naming Store/Create dos FormRequests por módulo.
 * Estende ChannelRequest preservando:
 * - Autorização `whatsapp.settings.manage` (perm Spatie)
 * - Regras per-type (zapi/meta/baileys) + LGPD `accepted_if` Baileys
 * - withValidator() cross-tenant que previne pareamento duplicado
 *   (ADR 0096 Baileys conflict-replaced + Gap B incidente 2026-05-13)
 *
 * Atualizações dessas regras devem ir em ChannelRequest (single source of truth).
 *
 * @see Modules\Whatsapp\Http\Requests\ChannelRequest
 * @see memory/decisions/0135-whatsapp-channels-arch.md
 */
class CreateChannelRequest extends ChannelRequest
{
}
