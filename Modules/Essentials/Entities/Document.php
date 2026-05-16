<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 12 D1 boost)
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
     * Auditoria LGPD (D7) — registra mudanças em documentos anexados a colaborador.
     * Documents contém PII alta (CNH/RG/comprovante endereço/contrato) — file_name
     * pode citar PII por convenção (ex: "rg-fulano.pdf"). Logamos apenas metadata
     * (status/sharing/timestamps), nunca conteúdo.
     *
     * @see Modules\Essentials\Config\retention.php
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'is_private', 'category', 'sub_category', 'created_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('essentials.document');
    }
}
