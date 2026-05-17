<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope herdada do core (esta Entity é proxy de App\<X> Eloquent; scope live no parent UltimatePOS, ADR 0093).


use Illuminate\Database\Eloquent\Model;

class TransactionSellLinesPurchaseLines extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function purchase_line()
    {
        return $this->belongsTo(\App\PurchaseLine::class, 'purchase_line_id');
    }
}
