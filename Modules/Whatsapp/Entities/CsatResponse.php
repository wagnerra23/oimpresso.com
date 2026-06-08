<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CsatResponse — pesquisa pós-atendimento 1-5 estrelas (PR-6 CYCLE-07).
 *
 * 1 row por conversa resolvida. Vida do row:
 *   1. `score=null` + `asked_at=now()` quando `CsatDispatcher::dispatchOnResolve`
 *      envia a mensagem CSAT outbound.
 *   2. `score=1..5` + `comment` + `responded_at=now()` quando webhook inbound
 *      parseia resposta válida via `CsatResponseParser`.
 *   3. Permanece `score=null` indefinido se cliente nunca responde — UI
 *      dashboard mostra como "% respondeu" denominador.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`. Webhook usa `withoutGlobalScopes()` com
 * comentário (sem session() user na callback).
 *
 * @property int $id
 * @property int $business_id
 * @property int $conversation_id
 * @property int $resolved_message_id
 * @property ?int $response_message_id
 * @property ?int $score
 * @property ?string $comment
 * @property ?int $resolved_by_user_id
 * @property \Carbon\CarbonImmutable $asked_at
 * @property ?\Carbon\CarbonImmutable $responded_at
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap #5 P1
 */
class CsatResponse extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_csat_responses';

    /** Score range — valida no parser e na UI. */
    public const SCORE_MIN = 1;
    public const SCORE_MAX = 5;

    /** Janela default pra idempotência dispatchOnResolve (24h). */
    public const DISPATCH_DEDUP_HOURS = 24;

    protected $fillable = [
        'business_id',
        'conversation_id',
        'resolved_message_id',
        'response_message_id',
        'score',
        'comment',
        'resolved_by_user_id',
        'asked_at',
        'responded_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'asked_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    /** Scope local — rows aguardando resposta (score IS NULL). */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('score');
    }

    /** Scope local — rows respondidas (score IS NOT NULL). */
    public function scopeResponded(Builder $query): Builder
    {
        return $query->whereNotNull('score');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'resolved_by_user_id');
    }

    public function responseMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'response_message_id');
    }
}
