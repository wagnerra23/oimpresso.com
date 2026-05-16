<?php

namespace Modules\AssetManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetTransaction extends Model
{
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Audit LGPD — registra mudanças via activity_log (D7.b dim v3 audit trail append-only).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * get asset for transaction
     */
    public function asset()
    {
        return $this->belongsTo('Modules\AssetManagement\Entities\Asset', 'asset_id');
    }

    public function revokeTransaction()
    {
        return $this->hasMany('Modules\AssetManagement\Entities\AssetTransaction', 'parent_id');
    }
}
