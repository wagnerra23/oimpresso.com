<?php

namespace Modules\SRS\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Wave 12 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 *
 * Tabela `docs_sources` tem coluna `business_id` (migration 2026_04_22_000001).
 * Trait `HasBusinessScope` aplica global scope automático — antes era
 * column-level enforcement (Controller-side via where('business_id', ...))
 * que era frágil. Agora é Model-level (Eloquent global scope) — defense
 * in depth (Wagner Tier 0 IRREVOGÁVEL).
 *
 * Pré-existente: MultiTenantIsolationTest SRS já cobre column-level — passa
 * tranquilo com scope adicional (scope refina, não muda contrato visível).
 */
class DocSource extends Model
{
    use HasBusinessScope;
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
