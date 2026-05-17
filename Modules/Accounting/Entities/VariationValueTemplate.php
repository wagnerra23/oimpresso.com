<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope herdada do core (esta Entity é proxy de App\<X> Eloquent; scope live no parent UltimatePOS, ADR 0093).


use Illuminate\Database\Eloquent\Model;

class VariationValueTemplate extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
    /**
     * Get the variation that owns the attribute.
     */
    public function variationTemplate()
    {
        return $this->belongsTo(\App\VariationTemplate::class);
    }
}
