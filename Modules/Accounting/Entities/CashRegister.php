<?php

namespace Modules\Accounting\Entities;

// WAVE 18 RETRY D1 MULTI-TENANT — Tier 0 IRREVOGÁVEL (ADR 0093)
// Tabela `cash_registers` tem business_id direto — trait HasBusinessScope aplica
// ScopeByBusiness global. Vazamento cross-tenant exporia saldo de caixa físico.

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 RETRY D1 MT saturation)

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
}
