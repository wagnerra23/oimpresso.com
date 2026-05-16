<?php

namespace Modules\RecurringBilling\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tentativa de cobrança — append-only log idempotente.
 *
 * Cada vez que GenerateInvoicesJob ou ChargeAttemptJob (US-RB-007 cartão
 * recorrente, US-RB-013 smart retry) tenta cobrar uma fatura, registra
 * uma linha aqui com (gateway, attempt_n, status, response_json).
 *
 * UNIQUE(invoice_id, attempt_n) garante idempotência ao reprocessar.
 *
 * status:
 *   pending        — criado mas não enviado ainda
 *   sent           — request enviado ao gateway, aguardando webhook
 *   succeeded      — gateway confirmou pagamento
 *   failed         — erro genérico (não retriable)
 *   soft_decline   — recusa retriable (cartão sem saldo, etc.) → smart retry
 *   hard_decline   — cartão inválido / fraude → marca subscription past_due
 *
 * LGPD (Wave 10 D7): LogsActivity registra apenas (gateway, status, error_code) —
 * NUNCA `request_json`/`response_json` (que carregam customer.cpfCnpj/email).
 */
class ChargeAttempt extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    public const UPDATED_AT = null; // append-only

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['gateway', 'status', 'attempt_n', 'error_code'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recurringbilling.charge_attempt');
    }

    protected $table = 'rb_charge_attempts';

    protected $fillable = [
        'business_id', 'invoice_id', 'gateway', 'attempt_n',
        'status', 'request_json', 'response_json',
        'error_code', 'error_message',
    ];

    protected $casts = [
        'attempt_n'     => 'integer',
        'request_json'  => 'array',
        'response_json' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isRetriable(): bool
    {
        return in_array($this->status, ['failed', 'soft_decline'], true);
    }
}
