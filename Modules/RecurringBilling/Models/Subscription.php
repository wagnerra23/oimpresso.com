<?php

namespace Modules\RecurringBilling\Models;

use App\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\ContaBancaria;

/**
 * Contrato de assinatura — vincula Contact (cliente) a um Plan + define
 * próximo vencimento + override opcional de gateway por conta_bancaria.
 *
 * Status canônicos:
 *   trialing  — período de teste, sem cobrança
 *   active    — cobrança regular
 *   paused    — pausado pelo cliente/admin (sem nova fatura)
 *   past_due  — fatura vencida sem pagamento (régua dunning ativa)
 *   canceled  — encerrado, fim do contrato
 */
class Subscription extends Model
{
    use SoftDeletes;

    protected $table = 'rb_subscriptions';

    protected $fillable = [
        'business_id', 'plan_id', 'contact_id', 'status',
        'start_date', 'next_due_date', 'billing_anchor_date',
        'canceled_at', 'paused_at',
        'conta_bancaria_id', 'metadata',
    ];

    protected $casts = [
        'start_date'           => 'date',
        'next_due_date'        => 'date',
        'billing_anchor_date'  => 'date',
        'canceled_at'          => 'datetime',
        'paused_at'            => 'datetime',
        'metadata'             => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'subscription_id');
    }

    public function scopeAtivas(Builder $q): Builder
    {
        return $q->whereIn('status', ['active', 'trialing', 'past_due']);
    }

    public function isAtiva(): bool
    {
        return in_array($this->status, ['active', 'trialing', 'past_due'], true);
    }
}
