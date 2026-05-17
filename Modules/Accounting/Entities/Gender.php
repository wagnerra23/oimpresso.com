<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope N/A — catálogo plataforma-wide (reference data global, sem scope per-business; ADR 0093).


use Illuminate\Database\Eloquent\Model;

class Gender extends Model
{
    public static function forDropdown()
    {
        return [
            'male' => __('accounting::core.male'),
            'female' => __('accounting::core.female'),
            'other' => __('accounting::core.other'),
            'unspecified' => __('accounting::core.unspecified'),
        ];
    }
}
