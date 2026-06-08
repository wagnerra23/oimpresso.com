<?php

namespace Modules\Superadmin\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SuperadminCommunicatorLog extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'business_ids' => 'array',
    ];

    /**
     * Auditoria LGPD D7.b — Wave 11 Superadmin.
     * Communicator dispara mensagens cross-tenant (email/notification em massa).
     * Trail append-only é Tier 0: vazamento de mensagem mass-direct a clientes
     * sem rastro = incidente legal (LGPD Art. 7º IX legítimo interesse vs
     * Art. 8º consentimento). Subject/message redactado via PiiRedactor antes
     * de log (se conter PII de qualquer destinatário).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('superadmin.communicator_log');
    }
}
