<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Channel — entidade canônica omnichannel polimórfica (ADR 0135).
 *
 * Substitui long-term `WhatsappBusinessPhone`. N rows por business, 1 por
 * canal cadastrado. Tipo discrimina driver + shape de `config_json`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`.
 *
 * `config_json` é cifrado em DB via cast `encrypted` Laravel.
 *
 * @property int $id
 * @property int $business_id
 * @property string $channel_uuid
 * @property string $label
 * @property string $type
 * @property string $status
 * @property ?string $display_identifier
 * @property ?array $config_json
 * @property bool $handles_repair_status
 * @property bool $handles_billing
 * @property bool $handles_jana_bot
 * @property bool $handles_outbound_default
 * @property bool $bot_enabled
 * @property string $channel_health
 * @property int $channel_health_consecutive_failures
 * @property ?\Carbon\CarbonImmutable $lgpd_acknowledged_at
 */
class Channel extends Model
{
    use HasBusinessScope;

    protected $table = 'channels';

    public const TYPE_WHATSAPP_META = 'whatsapp_meta';
    public const TYPE_WHATSAPP_ZAPI = 'whatsapp_zapi';
    public const TYPE_WHATSAPP_BAILEYS = 'whatsapp_baileys';
    public const TYPE_INSTAGRAM = 'instagram';
    public const TYPE_MESSENGER = 'messenger';
    public const TYPE_EMAIL_IMAP = 'email_imap';
    public const TYPE_EMAIL_SMTP = 'email_smtp';
    public const TYPE_MERCADOLIVRE = 'mercadolivre';

    public const TYPES = [
        self::TYPE_WHATSAPP_META,
        self::TYPE_WHATSAPP_ZAPI,
        self::TYPE_WHATSAPP_BAILEYS,
        self::TYPE_INSTAGRAM,
        self::TYPE_MESSENGER,
        self::TYPE_EMAIL_IMAP,
        self::TYPE_EMAIL_SMTP,
        self::TYPE_MERCADOLIVRE,
    ];

    protected $fillable = [
        'business_id', 'channel_uuid', 'label', 'type', 'status',
        'display_identifier', 'config_json',
        'handles_repair_status', 'handles_billing', 'handles_jana_bot', 'handles_outbound_default',
        'bot_enabled',
        'template_repair_ready_name', 'template_repair_waiting_parts_name',
        'template_billing_due_name', 'template_billing_paid_name',
        'channel_health', 'channel_health_consecutive_failures',
        'last_health_check_at', 'last_health_message',
        'lgpd_acknowledged_at', 'lgpd_acknowledged_by_user_id',
    ];

    protected $casts = [
        'config_json' => 'encrypted:array',
        'handles_repair_status' => 'boolean',
        'handles_billing' => 'boolean',
        'handles_jana_bot' => 'boolean',
        'handles_outbound_default' => 'boolean',
        'bot_enabled' => 'boolean',
        'channel_health_consecutive_failures' => 'integer',
        'last_health_check_at' => 'datetime',
        'lgpd_acknowledged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $channel): void {
            if (empty($channel->channel_uuid)) {
                $channel->channel_uuid = (string) Str::uuid();
            }
        });
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function isWhatsapp(): bool
    {
        return in_array($this->type, [
            self::TYPE_WHATSAPP_META,
            self::TYPE_WHATSAPP_ZAPI,
            self::TYPE_WHATSAPP_BAILEYS,
        ], true);
    }

    /**
     * Convenção canônica do instance_id no daemon Baileys CT 100:
     * `ch-{channel_uuid sem hífens}`.
     *
     * Centralizado aqui pra evitar drift entre ChannelObserver (que dispatcha
     * DeleteBaileysInstanceJob) e qualquer futuro código que precise resolver
     * o instance_id correspondente a um Channel. Caso real validado em
     * produção 2026-05-13: `ch-88b13697b89e451cb65be917533bab21` (MARTINHO
     * CAÇAMBAS biz=164).
     *
     * Retorna null quando `channel_uuid` ausente OU type ≠ baileys (defensive —
     * Z-API e Meta Cloud não rodam no daemon CT 100).
     *
     * @see Modules/Whatsapp/Observers/ChannelObserver.php
     * @see Modules/Whatsapp/Jobs/DeleteBaileysInstanceJob.php
     */
    public function baileysInstanceId(): ?string
    {
        if ($this->type !== self::TYPE_WHATSAPP_BAILEYS) {
            return null;
        }
        if (empty($this->channel_uuid)) {
            return null;
        }
        return 'ch-' . str_replace('-', '', (string) $this->channel_uuid);
    }
}
