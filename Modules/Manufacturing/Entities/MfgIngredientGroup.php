<?php

namespace Modules\Manufacturing\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MfgIngredientGroup extends Model
{
    use LogsActivity; // D7.b LGPD — audit trail grupo ingrediente (Wave S Batch 2)

    /**
     * Spatie ActivityLog — registra mudanças no grupo de ingredientes.
     *
     * business_id chain via JOIN products (catálogo) — não mexer no scope.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'description',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('manufacturing.ingredient_group');
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
}
