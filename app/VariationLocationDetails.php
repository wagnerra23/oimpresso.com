<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VariationLocationDetails extends Model
{
    use LogsActivity;

    /**
     * Activity log config — captura ajustes de estoque (qty_available) com
     * diff old/new pra rastrear movimentacoes. Padrao Modules/Financeiro/Titulo.
     *
     * Refs: ADR 0127 (Modules/Auditoria UI + undo) US-AUDIT-002
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['qty_available'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('inventory.stock');
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
}
