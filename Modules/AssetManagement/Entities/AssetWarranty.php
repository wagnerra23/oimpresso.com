<?php

namespace Modules\AssetManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetWarranty extends Model
{
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD — registra mudanças em garantias (D7.b dim v3 audit trail append-only).
     * Whitelist evita logar campos descritivos longos (notes pode conter PII inadvertida).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['asset_id', 'name', 'start_date', 'end_date', 'business_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('assetmanagement.warranty');
    }

    public function getMonthsAttribute()
    {
        return \Carbon::parse($this->start_date)->diffInMonths($this->end_date);
    }
}
