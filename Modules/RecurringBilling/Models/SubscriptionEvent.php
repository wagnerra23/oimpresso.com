<?php

namespace Modules\RecurringBilling\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Timeline append-only de eventos por assinatura.
 * Mistura eventos automáticos (sistema/SEFAZ) com notas humanas (Eliana/Wagner/contact).
 *
 * Kinds canônicos (visual canon recurring-data.jsx TIMELINES):
 *   event-create   — assinatura criada
 *   event-status   — mudança de status (cancelada/pausada/reativada)
 *   event-plan     — mudança de plano (upgrade/downgrade)
 *   event-charge   — cobrança disparada/paga
 *   event-retry    — tentativa de re-cobrança
 *   event-nf       — emissão de NFe/NFS-e (e reenvios)
 *   note           — nota livre humano
 *
 * Append-only: sem update/delete (regra cultural — Pest test garante).
 * Multi-tenant Tier 0 IRREVOGÁVEL: business_id global scope.
 */
class SubscriptionEvent extends Model
{
    use HasBusinessScope;

    protected $table = 'rb_subscription_events';

    protected $fillable = [
        'business_id', 'subscription_id', 'kind',
        'by_actor', 'body', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public const KIND_CREATE  = 'event-create';
    public const KIND_STATUS  = 'event-status';
    public const KIND_PLAN    = 'event-plan';
    public const KIND_CHARGE  = 'event-charge';
    public const KIND_RETRY   = 'event-retry';
    public const KIND_NF      = 'event-nf';
    public const KIND_NOTE    = 'note';

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function scopeRecent(Builder $q, int $limit = 20): Builder
    {
        return $q->orderByDesc('occurred_at')->limit($limit);
    }
}
