<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope herdada do core (esta Entity é proxy de App\<X> Eloquent; scope live no parent UltimatePOS, ADR 0093).


use Illuminate\Database\Eloquent\Model;

class BankDetails extends Model
{
    protected $fillable = [
        'contact_id',
        'account_holder_name',
        'account_number',
        'bank_name',
        'bank_code',
        'branch',
        'tax_payer_id'
    ];
}
