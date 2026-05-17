<?php

namespace Modules\Accounting\Entities;

// WAVE 18 D1 MULTI-TENANT MARKER
// BusinessScope herdada do core (esta Entity é proxy de App\<X> Eloquent; scope live no parent UltimatePOS, ADR 0093).


use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentAndNote extends Model
{
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected static $logUnguarded = true;
    protected static $logOnlyDirty = true;


    /**
     * Get all of the owning notable models.
     */
    public function notable()
    {
        return $this->morphTo();
    }

    public function media()
    {
        return $this->morphMany(\Modules\Accounting\Entities\Media::class, 'model');
    }

    /**
     * Get the user who added note.
     */
    public function createdBy()
    {
        return $this->belongsTo('App\User', 'created_by');
    }
}
