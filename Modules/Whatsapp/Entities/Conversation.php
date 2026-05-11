<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 */
class Conversation extends Model
{
    use HasBusinessScope;

    protected $table = 'conversations';

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
        'unread_count',
    ];

    protected $casts = [
        'bot_handling' => 'boolean',
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
}
