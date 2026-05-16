<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    // Wave 11 LGPD (D7.b) — audit trail Spatie ActivityLog.
    // Documentos (CC Art. 206 — prescrição 5 anos; LGPD Art. 16) exigem trilha auditável.
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'essentials_documents';

    /**
     * Wave 11 LGPD (D7.b) — log apenas metadados (não o file path ou conteúdo).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['file_name', 'file_type', 'description', 'business_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
