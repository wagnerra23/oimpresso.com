<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope pendente migração HasBusinessScope (Wave 18 — sem column business_id direta no schema dev; auditoria EntityBusinessIdConsistencyTest valida real; ADR 0093).


use Illuminate\Database\Eloquent\Model;

class CashRegisterTransaction extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
}
