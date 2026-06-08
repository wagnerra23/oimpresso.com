<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CrmContactPersonCommission extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * Auditoria LGPD — registra mudanças em comissões de contact person (toca user_id + valor).
     * D7 LGPD compliance (audit trail append-only via activity_log).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm.contact_person_commission');
    }
}
