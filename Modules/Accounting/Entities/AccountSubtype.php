<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;

class AccountSubtype extends Model
{
    // ADR 0093 — NÃO usa HasBusinessScope: semântica `business_id=0 = catálogo plataforma`
    // visível pra TODOS os tenants (Wave 13 HasBusinessScopeAdoptionTest documenta).
    // Isolamento garantido via scope explícito `forBusiness()` em Controllers.

    protected $fillable = [
        'business_id',
        'account_type',
        'name',
        'description',
        'active'
    ];

    public function getAccountTypeNameAttribute()
    {
        return ucfirst($this->account_type);
    }

    public function scopeActive($query)
    {
        return $query->where('account_subtypes.active', 1);
    }

    public function scopeForBusiness($query)
    {
        return $query->where('account_subtypes.business_id', 0)
            ->orWhere('account_subtypes.business_id', session('business.id'));
    }

    public function getIsDefaultTypeAttribute()
    {
        return $this->business_id == 0;
    }
}
