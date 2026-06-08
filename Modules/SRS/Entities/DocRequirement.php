<?php

namespace Modules\SRS\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Wave 12 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 * Wave 27 — D1 expand LogsActivity (audit trail LGPD governance — paridade com DocSource/DocChatMessage).
 *
 * Tabela `docs_requirements` tem coluna `business_id` (migration
 * 2026_04_22_000003). Trait `HasBusinessScope` aplica global scope automático —
 * antes column-level (Controller-side), agora Model-level (Eloquent). Defense in depth.
 *
 * LogsActivity (Wave 27): user stories podem ser ajustadas ao longo da vida do
 * projeto — auditar quem mudou o que (titulo/descricao/status) preserva
 * rastreabilidade pra governance + ADR/CHANGELOG cross-check.
 */
class DocRequirement extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'docs_requirements';
    protected $guarded = ['id'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * Wave 27 — audit trail LGPD pra mudancas em user stories/requirements.
     *
     * D7 LGPD: mesmo SRS sendo ferramenta interna Wagner sem PII grave,
     * requirements descrevem features que TOCAM dados de cliente — auditar
     * mudancas no contrato (story/rule) preserva rastreabilidade fiscal.
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
        return $this->belongsToMany(
            DocEvidence::class,
            'docs_links',
            'requirement_id',
            'evidence_id'
        )->withPivot('role')->withTimestamps();
    }
}
