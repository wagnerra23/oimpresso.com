<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SlaPolicy — CYCLE-07 PR-2 (Gap P0 #2 COMPARATIVO-MERCADO-2026-05-12).
 *
 * 1 row por política de SLA cadastrada num business. `SlaEnforcer::scanAndAlert()`
 * itera policies ativas, varre conversas que violam threshold, dispara action.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`. Scan job usa `withoutGlobalScopes()` com
 * comentário SUPERADMIN (job sem session, cross-tenant by-design).
 *
 * @property int $id
 * @property int $business_id
 * @property string $label
 * @property int $threshold_minutes
 * @property string $triggers_on
 * @property ?int $channel_id
 * @property ?int $tag_id
 * @property string $action_kind
 * @property ?array $action_params
 * @property bool $active
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md
 * @see Modules/Whatsapp/Services/Sla/SlaEnforcer.php
 */
class SlaPolicy extends Model
{
    use HasBusinessScope;

    protected $table = 'sla_policies';

    public const TRIGGER_FIRST_INBOUND_NO_REPLY = 'first_inbound_no_reply';
    public const TRIGGER_OPEN_AGING = 'open_aging';
    public const TRIGGER_AWAITING_HUMAN_AGING = 'awaiting_human_aging';

    public const TRIGGERS = [
        self::TRIGGER_FIRST_INBOUND_NO_REPLY,
        self::TRIGGER_OPEN_AGING,
        self::TRIGGER_AWAITING_HUMAN_AGING,
    ];

    public const ACTION_CENTRIFUGO_NOTIFY = 'centrifugo_notify';
    public const ACTION_REASSIGN = 'reassign';
    public const ACTION_SET_STATUS = 'set_status';

    public const ACTIONS = [
        self::ACTION_CENTRIFUGO_NOTIFY,
        self::ACTION_REASSIGN,
        self::ACTION_SET_STATUS,
    ];

    protected $fillable = [
        'business_id',
        'label',
        'threshold_minutes',
        'triggers_on',
        'channel_id',
        'tag_id',
        'action_kind',
        'action_params',
        'active',
    ];

    protected $casts = [
        'threshold_minutes' => 'integer',
        'channel_id' => 'integer',
        'tag_id' => 'integer',
        'action_params' => 'array',
        'active' => 'boolean',
    ];

    /** Scope local — apenas policies ativas. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
