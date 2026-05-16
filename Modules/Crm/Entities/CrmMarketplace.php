<?php

namespace Modules\Crm\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CrmMarketplace extends Model
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
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'assigned_users' => 'array',
    ];

    /**
     * Auditoria LGPD â€” registra mudanÃ§as em marketplaces CRM (atribuiÃ§Ãµes de usuÃ¡rios).
     * D7 LGPD compliance (audit trail append-only via activity_log).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm.marketplace');
    }
}
