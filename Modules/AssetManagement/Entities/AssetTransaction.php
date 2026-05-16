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
     * Auditoria LGPD — registra movimentações allocate/revoke vinculando user a asset
     * (D7.b dim v3 audit trail append-only). Whitelist explícita pra reduzir ruído.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'asset_id', 'business_id', 'transaction_type', 'allocated_to',
                'quantity', 'transaction_date', 'parent_id', 'created_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('assetmanagement.transaction');
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
