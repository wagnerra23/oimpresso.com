<?php

namespace Modules\Accounting\Entities;

// WAVE 18 RETRY D1 MULTI-TENANT — Tier 0 IRREVOGÁVEL (ADR 0093)
// Tabela `discounts` tem business_id direto — trait HasBusinessScope aplica
// ScopeByBusiness global. Desconto leak cross-tenant = preço errado outras empresas.

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 RETRY D1 MT saturation)

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['starts_at', 'ends_at'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function variations()
    {
        return $this->belongsToMany(\App\Variation::class, 'discount_variations', 'discount_id', 'variation_id');
    }
}
