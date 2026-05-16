<?php

namespace Modules\RecurringBilling\Models;

use App\Contact;
use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\ContaBancaria;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
 *
 * LGPD (Wave 10 D7): LogsActivity (Spatie) registra mudanças críticas
 * (status, plano, vencimento, valores cached, churn) em `activity_log` —
 * auditoria 5 anos (Art. 195 CTN + LGPD Art. 37 § 1º).
 */
class Subscription extends Model
{
    use HasBusinessScope;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'plan_id', 'next_due_date', 'canceled_at', 'paused_at',
                'payment_method', 'total_paid_cached', 'churn_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recurringbilling.subscription');
    }

    protected $table = 'rb_subscriptions';

    protected $fillable = [
        'business_id', 'plan_id', 'contact_id', 'status',
        'start_date', 'next_due_date', 'billing_anchor_date',
        'canceled_at', 'paused_at',
        'conta_bancaria_id', 'metadata',
        // v9,75 — Onda 1 schema aditivo
        'payment_method', 'last_jobsheet_id',
        'total_paid_cached', 'failed_count_cached', 'total_revenue_cached',
        'paused_until', 'churn_reason', 'contact_phone_cached',
    ];

    protected $casts = [
        'start_date'           => 'date',
        'next_due_date'        => 'date',
        'billing_anchor_date'  => 'date',
        'canceled_at'          => 'datetime',
        'paused_at'            => 'datetime',
        'metadata'             => 'array',
        // v9,75
        'paused_until'         => 'date',
        'total_revenue_cached' => 'decimal:2',
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

    public function notes(): HasMany
    {
        return $this->hasMany(SubscriptionNote::class, 'subscription_id');
    }

    public function pinnedNote()
    {
        return $this->hasOne(SubscriptionNote::class, 'subscription_id')
            ->where('is_pinned', true)
            ->latest('updated_at');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(SubscriptionFavorite::class, 'subscription_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class, 'subscription_id');
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
