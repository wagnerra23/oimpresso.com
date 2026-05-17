<?php

namespace Modules\Accounting\Entities;

// WAVE 18 RETRY D1 MULTI-TENANT — Tier 0 IRREVOGÁVEL (ADR 0093)
// Tabela `cash_registers` tem business_id direto — trait HasBusinessScope aplica
// ScopeByBusiness global. Vazamento cross-tenant exporia saldo de caixa físico.

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashRegister extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 RETRY D1 MT saturation)
    use LogsActivity;     // Wave 25 D7.b — audit trail append-only (LGPD compliance)

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'denominations' => 'array'
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the Cash registers transactions.
     */
    public function cash_register_transactions()
    {
        return $this->hasMany(\App\CashRegisterTransaction::class);
    }

    /**
     * Audit LGPD (Wave 25 D7.b) — rastreia status/closing_amount/initial_amount
     * (sensível: caixa físico = vínculo PII operador). PiiRedactor é aplicado
     * downstream pelo job `accounting:retention-purge` (Config/retention.php).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'closing_amount', 'initial_amount', 'closed_at', 'location_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
