<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope pendente migração HasBusinessScope (Wave 18 — sem column business_id direta no schema dev; auditoria EntityBusinessIdConsistencyTest valida real; ADR 0093).


use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashRegisterTransaction extends Model
{
    use LogsActivity; // Wave 25 D7.b — audit trail append-only (LGPD compliance)

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Audit LGPD (Wave 25 D7.b) — registra todas mudanças (amount/type/transaction_type).
     * Pivot caixa × transação; importante pra reconciliação contábil e rastreabilidade
     * fiscal CTN Art. 195 (5 anos prescrição). Sem PII direta — auditoria livre.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
