<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * LidPhoneMap — cache custom LID (WhatsApp Multi-Device Linked ID) → phone E.164.
 *
 * Workaround pra Baileys 6.7.9 não expor LID Alt JID nativo (chega só em
 * v7.x). Webhook persiste o par quando WhatsApp envia `senderPn` ao lado de
 * `remoteJid@lid`; próximas msgs do mesmo LID resolvem o phone real via
 * cache em vez de exibir o LID anônimo na UI.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`. UNIQUE (business_id, lid) na migration
 * impede vazamento cross-tenant.
 *
 * @property int $id
 * @property int $business_id
 * @property string $lid
 * @property ?string $phone_e164
 * @property string $source
 * @property \Carbon\CarbonImmutable $first_seen_at
 * @property \Carbon\CarbonImmutable $last_seen_at
 *
 * @see Modules\Whatsapp\Services\Contacts\LidPhoneResolver
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class LidPhoneMap extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_lid_pn_map';

    public const SOURCE_WEBHOOK_SENDER_PN = 'webhook_senderPn';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_BAILEYS_LOOKUP = 'baileys_lookup';

    protected $fillable = [
        'business_id',
        'lid',
        'phone_e164',
        'source',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
