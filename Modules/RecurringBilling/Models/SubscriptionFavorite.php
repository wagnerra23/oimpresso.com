<?php

namespace Modules\RecurringBilling\Models;

use App\Concerns\HasBusinessScope;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Favorito pessoal por operador — UNIQUE(user_id, subscription_id).
 *
 * Suporta filtro `Mostrar só favoritos` da sidebar (visual canon screenshot Wagner 2026-05-16).
 * Eliana tem 3-5 assinantes que monitora diário.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL: business_id global scope.
 */
class SubscriptionFavorite extends Model
{
    use HasBusinessScope;

    public $timestamps = false;

    protected $table = 'rb_subscription_favorites';

    protected $fillable = [
        'business_id', 'subscription_id', 'user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
