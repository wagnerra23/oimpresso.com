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
 * Fatura — uma cobrança individual gerada por uma Subscription
 * (ou avulsa, sem subscription_id quando o operador cobrar uma única vez).
 *
 * `gateway` + `gateway_ref` são populados após a 1ª ChargeAttempt.
 * `numero_documento` é o display público "INV-2026-0001".
 *
 * Triggered events:
 *   - InvoicePaid (status=paid) — listener em NfeBrasil emite NFe (US-RB-044)
 *
 * LGPD (Wave 10 D7): LogsActivity registra mudanças de status (paid/overdue/cancelled),
 * valor e vencimento — retenção 5 anos pra suportar contestação fiscal/Receita.
 */
class Invoice extends Model
{
    use HasBusinessScope;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'valor', 'vencimento', 'pago_em', 'gateway', 'gateway_ref'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recurringbilling.invoice');
    }

    protected $table = 'rb_invoices';

    protected $fillable = [
        'business_id', 'subscription_id', 'contact_id',
        'numero_documento', 'valor', 'status',
        'vencimento', 'pago_em',
        'gateway', 'gateway_ref',
        'conta_bancaria_id', 'metadata',
    ];

    protected $casts = [
        'valor'      => 'decimal:2',
        'vencimento' => 'date',
        'pago_em'    => 'datetime',
        'metadata'   => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class, 'conta_bancaria_id');
    }

    public function chargeAttempts(): HasMany
    {
        return $this->hasMany(ChargeAttempt::class, 'invoice_id');
    }

    public function scopeAbertas(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'overdue']);
    }

    public function scopeVencidas(Builder $q): Builder
    {
        return $q->where('status', 'open')
            ->where('vencimento', '<', now()->toDateString());
    }

    public function isPaga(): bool
    {
        return $this->status === 'paid';
    }
}
