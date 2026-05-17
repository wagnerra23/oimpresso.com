<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope N/A — catálogo plataforma-wide (reference data global, sem scope per-business; ADR 0093).


use App\Currency as AppCurrency;

class Currency extends AppCurrency
{
    protected $table = "currencies";
    protected $fillable = [];

    public function getNameAttribute()
    {
        $split_currency = explode("-", $this->currency);

        if (!strlen($split_currency[1]) > 0) {
            return "";
        }

        $split_name_code =  explode("(", $split_currency[1]);
        $name = trim($split_name_code[0]);

        return $name;
    }

    public function getCurrencyAttribute($value)
    {
        return "{$this->country} - {$value}({$this->code})";
    }
}
