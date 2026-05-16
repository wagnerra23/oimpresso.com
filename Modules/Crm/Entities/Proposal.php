<?php

namespace Modules\Crm\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Proposal extends Model
{
    use HasBusinessScope; // ADR 0093 â€” multi-tenant Tier 0 IRREVOGÃVEL (defesa-em-profundidade; soma ao where('business_id') explÃ­cito dos Controllers)
    use LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_proposals';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD â€” registra mudanÃ§as em propostas (toca PII: contato + valores).
     * D7 LGPD compliance (audit trail append-only via activity_log).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm.proposal');
    }

    public function media()
    {
        return $this->morphMany(\App\Media::class, 'model');
    }
}
