<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ConversationMetric — snapshot diário agregado de métricas omnichannel
 * (US-WA-021/041, CYCLE-07 PR-3).
 *
 * Cada row representa uma tupla (business_id, metric_date, channel_id).
 * Quando `channel_id` é null, a row é o agregado do business inteiro
 * naquele dia (soma de todos os canais).
 *
 * Por que existe — Constituição §4 "loop fechado por métrica". Sem este
 * snapshot, dashboard `/atendimento/metricas` cobraria scan completo de
 * `messages` + `conversations` em runtime (impraticável a partir de 10k
 * mensagens/dia).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait HasBusinessScope. NUNCA usar `withoutGlobalScope` sem comentário.
 *
 * @property int $id
 * @property int $business_id
 * @property \Illuminate\Support\Carbon $metric_date
 * @property ?int $channel_id
 * @property int $conversations_opened
 * @property int $conversations_resolved
 * @property int $messages_inbound
 * @property int $messages_outbound
 * @property ?int $avg_first_response_seconds
 * @property ?int $avg_resolution_seconds
 * @property int $total_cost_centavos
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap P0 #4
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-021/041
 */
class ConversationMetric extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_conversation_metricas';

    protected $fillable = [
        'business_id',
        'metric_date',
        'channel_id',
        'conversations_opened',
        'conversations_resolved',
        'messages_inbound',
        'messages_outbound',
        'avg_first_response_seconds',
        'avg_resolution_seconds',
        'total_cost_centavos',
    ];

    protected $casts = [
        'metric_date' => 'date:Y-m-d',
        'business_id' => 'integer',
        'channel_id' => 'integer',
        'conversations_opened' => 'integer',
        'conversations_resolved' => 'integer',
        'messages_inbound' => 'integer',
        'messages_outbound' => 'integer',
        'avg_first_response_seconds' => 'integer',
        'avg_resolution_seconds' => 'integer',
        'total_cost_centavos' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
