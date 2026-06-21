<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * WhatsappBroadcast — campanha broadcast cross-canal (US-WA-306 · ADR 0268).
 *
 * FASE 1 (scaffold honesto): só `status=draft` é gravado — pre-flight calcula
 * audiência (opt-in LGPD + janela 24h Meta) e congela snapshot auditável.
 * FASE 2 (gate [W]): Job rate-limited transita draft→dispatching→done.
 *
 * Multi-tenant Tier 0 (ADR 0093) via HasBusinessScope.
 *
 * @property int $id
 * @property int $business_id
 * @property int $channel_id
 * @property int $created_by_user_id
 * @property string $kind
 * @property ?string $template_name
 * @property ?string $body
 * @property string $status
 * @property array $audience_snapshot
 * @property array $recipient_conversation_ids
 * @property ?\Illuminate\Support\Carbon $dispatched_at
 */
class WhatsappBroadcast extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_broadcasts';

    public const KINDS = ['freeform', 'template'];
    public const STATUSES = ['draft', 'dispatching', 'done', 'failed'];

    protected $fillable = [
        'business_id', 'channel_id', 'created_by_user_id',
        'kind', 'template_name', 'body', 'status',
        'audience_snapshot', 'recipient_conversation_ids', 'dispatched_at',
    ];

    protected $casts = [
        'audience_snapshot' => 'array',
        'recipient_conversation_ids' => 'array',
        'dispatched_at' => 'datetime',
    ];
}
