<?php

namespace Modules\AssetManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetMaintenance extends Model
{
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD — registra mudanças em manutenções (D7.b dim v3 audit trail append-only).
     * Whitelist evita logar `maintenance_note` que pode conter dados sensíveis técnicos/clientes.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'asset_id', 'business_id', 'assigned_to', 'created_by',
                'start_date', 'end_date', 'status', 'amount',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('assetmanagement.maintenance');
    }

    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }

    /**
     * user added asset.
     */
    public function asset()
    {
        return $this->belongsTo(\Modules\AssetManagement\Entities\Asset::class, 'asset_id');
    }

    /**
     * user added asset maintence.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * user assigned asset maintence.
     */
    public function assignedTo()
    {
        return $this->belongsTo(\App\User::class, 'assigned_to');
    }
}
