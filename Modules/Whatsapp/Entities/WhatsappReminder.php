<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WhatsappReminder — US-WA-076 (ADR 0142 §5).
 *
 * 1 row por lembrete criado via slash `/lembrete <data> <body>` em nota
 * interna do atendimento. `ProcessRemindersJob` hourly varre rows com
 * `status='pending'` AND `due_at<=now()` AND `notified_at IS NULL` →
 * publica Centrifugo no canal `user:{atendente_user_id}`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`. O Job DEVE usar `withoutGlobalScopes()`
 * com comentário (cross-tenant scanning sem auth).
 *
 * @property int $id
 * @property int $business_id
 * @property int $conversation_id
 * @property ?int $contact_id
 * @property int $atendente_user_id
 * @property int $created_by_user_id
 * @property \Carbon\CarbonImmutable $due_at
 * @property string $body
 * @property string $status
 * @property ?\Carbon\CarbonImmutable $notified_at
 * @property ?\Carbon\CarbonImmutable $completed_at
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-076
 */
class WhatsappReminder extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_reminders';

    public const STATUS_PENDING = 'pending';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_NOTIFIED,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'business_id',
        'conversation_id',
        'contact_id',
        'atendente_user_id',
        'created_by_user_id',
        'due_at',
        'body',
        'status',
        'notified_at',
        'completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'notified_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Scope local — rows pendentes (status=pending). */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope local — rows pendentes vencidas (due_at <= now()).
     *
     * Usado pelo ProcessRemindersJob pra varrer o que vence.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('due_at', '<=', now());
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'atendente_user_id');
    }
}
