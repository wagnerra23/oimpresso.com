<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Conversation — entidade canônica omnichannel (ADR 0135).
 *
 * Substitui long-term `WhatsappConversation`. 1 conversa = tripla
 * (business, channel, customer_external_id). `customer_external_id` é
 * polimórfico (E.164 phone | fb_user_id | email | ml_buyer_id).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`.
 *
 * @property int $id
 * @property int $business_id
 * @property int $channel_id
 * @property ?int $contact_id
 * @property string $customer_external_id
 * @property ?string $contact_name
 * @property string $status
 * @property ?int $assigned_user_id
 * @property bool $bot_handling
 * @property ?\Carbon\CarbonImmutable $last_inbound_at
 * @property ?\Carbon\CarbonImmutable $last_outbound_at
 * @property ?\Carbon\CarbonImmutable $last_message_at
 * @property int $unread_count
 * @property ?string $last_message_preview
 * @property ?string $last_message_direction
 * @property ?string $lid          PR1 — WhatsApp LID (`<random>@lid` sem sufixo `@lid`) anonymized account-level
 * @property ?string $phone_e164   PR1 — Telefone real `+E.164` quando resolvido (via senderPn ou LidPhoneResolver)
 * @property ?string $bsuid        PR1 — Cloud API `user_id` (Meta-oficial desde 31-mar-2026)
 */
class Conversation extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'conversations';

    /**
     * Auditoria LGPD D7 — registra mudanças relevantes na conversa
     * (atribuição atendente, status open/resolved/archived, bloqueio,
     * toggle bot_handling). NÃO loga `contact_name`, `last_message_preview`
     * nem `customer_external_id` (PII intensa — fica no banco redacted
     * conforme COMPLIANCE.md retention 180d). Append-only via `activity_log`.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'assigned_user_id', 'bot_handling',
                'is_blocked', 'unread_count',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public const STATUS_OPEN = 'open';
    public const STATUS_AWAITING_HUMAN = 'awaiting_human';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_AWAITING_HUMAN,
        self::STATUS_RESOLVED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'business_id', 'channel_id', 'contact_id',
        'customer_external_id', 'contact_name',
        'status', 'assigned_user_id', 'bot_handling',
        'last_inbound_at', 'last_outbound_at', 'last_message_at',
        'unread_count', 'is_blocked',
        // US-WA-072 — denormalizado pra evitar N+1 em InboxController list
        'last_message_preview', 'last_message_direction',
        // PR1 — schema 3-identifiers (LID/phone/BSUID) — estudo protocol-level 2026-05-15
        'lid', 'phone_e164', 'bsuid',
    ];

    protected $casts = [
        'bot_handling' => 'boolean',
        'is_blocked' => 'boolean',
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Tags aplicadas a esta conversa (US-WA-063).
     *
     * Many-to-many via pivot `whatsapp_conversation_tags`. Atendente toggle
     * tags do sidebar direito; filtro nas tabs do Inbox usa `whereHas('tags')`.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'whatsapp_conversation_tags',
            'conversation_id',
            'tag_id',
        )->withPivot('created_by_user_id')->withTimestamps();
    }
}
