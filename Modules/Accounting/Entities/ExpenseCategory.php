<?php

namespace Modules\Accounting\Entities;

// WAVE 18 RETRY D1 MULTI-TENANT — Tier 0 IRREVOGÁVEL (ADR 0093)
// Tabela `expense_categories` tem business_id direto — trait HasBusinessScope aplica
// ScopeByBusiness global, segue padrão Account/TaxRate/Budget (Waves 12+13).

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 RETRY D1 MT saturation)
    use SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
}
