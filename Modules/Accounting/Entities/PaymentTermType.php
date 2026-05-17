<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope N/A — catálogo plataforma-wide (reference data global, sem scope per-business; ADR 0093).


use Illuminate\Database\Eloquent\Model;

class PaymentTermType extends Model
{
    public static function forDropdown()
    {
        return [
            'months' => __('lang_v1.months'),
            'days' => __('lang_v1.days')
        ];
    }
}
