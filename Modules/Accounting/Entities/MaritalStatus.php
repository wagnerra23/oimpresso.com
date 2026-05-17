<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope N/A — catálogo plataforma-wide (reference data global, sem scope per-business; ADR 0093).


use Illuminate\Database\Eloquent\Model;

class MaritalStatus extends Model
{
    public static function forDropdown()
    {
        return [
            'single' => __('accounting::core.single'),
            'married' => __('accounting::core.married'),
            'divorced' => __('accounting::core.divorced'),
            'widowed' => __('accounting::core.widowed'),
        ];
    }
}
