<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope pendente migração HasBusinessScope (Wave 18 — sem column business_id direta no schema dev; auditoria EntityBusinessIdConsistencyTest valida real; ADR 0093).


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentAccount extends Model
{
    use SoftDeletes;

    public static function account_types()
    {
        return [
            'cash' => trans("lang_v1.cash"), 'card' => trans("lang_v1.card"),
            'cheque' => trans("lang_v1.cheque"), 'bank_transfer' => trans("lang_v1.bank_transfer"),
            'payment_gateway' => trans("lang_v1.payment_gateway"), 'other' => trans("lang_v1.other")
        ];
    }

    public static function account_name($type)
    {
        $types = PaymentAccount::account_types();

        return isset($types[$type]) ? $types[$type] : $type;
    }
}
