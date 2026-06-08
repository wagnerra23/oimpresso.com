<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ScheduleLog extends Model
{
    use LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_schedule_logs';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD — registra mudanças em logs de follow-up (descrição + outcome).
     * D7 LGPD compliance (audit trail append-only via activity_log).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm.schedule_log');
    }

    /**
     * Get the post that owns the comment.
     */
    public function schedule()
    {
        return $this->belongsTo('Modules\Crm\Entities\Schedule', 'schedule_id');
    }

    /**
     * user who created a schedule log.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }
}
