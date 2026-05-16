<?php

namespace Modules\RecurringBilling\Models;

use App\Concerns\HasBusinessScope;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nota interna por assinatura — visível só pra equipe (não para cliente final).
 * Suporta `is_pinned` pra fixar 1 nota no topo do drawer detalhe (visual canon screenshot Wagner 2026-05-16).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL: business_id global scope.
 */
class SubscriptionNote extends Model
{
    use HasBusinessScope;

    use SoftDeletes;

    protected $table = 'rb_subscription_notes';

    protected $fillable = [
        'business_id', 'subscription_id', 'user_id',
        'body', 'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePinned(Builder $q): Builder
    {
        return $q->where('is_pinned', true);
    }
}
