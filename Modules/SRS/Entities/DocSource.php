<?php

namespace Modules\SRS\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocSource extends Model
{
    use LogsActivity;

    protected $table = 'docs_sources';
    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Auditoria LGPD — registra mudanças em fontes de documentação SRS.
     *
     * D7 LGPD compliance (audit trail append-only via activity_log).
     * Embora SRS seja ferramenta interna Wagner sem PII grave, fontes de doc
     * podem referenciar URLs externas / arquivos com nome próprio — logar
     * mudanças permite rastrear quem ingeriu o quê.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function evidences()
    {
        return $this->hasMany(DocEvidence::class, 'source_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }
}
