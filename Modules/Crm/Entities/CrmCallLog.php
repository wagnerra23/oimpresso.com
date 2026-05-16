<?php

namespace Modules\Crm\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CrmCallLog extends Model
{
    use HasBusinessScope; // ADR 0093 â€” multi-tenant Tier 0 IRREVOGÃVEL (defesa-em-profundidade; soma ao where('business_id') explÃ­cito dos Controllers)
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD â€” registra mudanÃ§as em call logs CRM (toca PII: telefone, gravaÃ§Ã£o).
     * D7 LGPD compliance (audit trail append-only via activity_log).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm.call_log');
    }
}
