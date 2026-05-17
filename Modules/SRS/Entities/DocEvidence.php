<?php

namespace Modules\SRS\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Wave 12 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 * Wave 27 — D1 expand LogsActivity (audit trail LGPD — paridade DocSource/DocRequirement).
 *
 * Tabela `docs_evidences` tem coluna `business_id` (migration
 * 2026_04_22_000002). Trait `HasBusinessScope` aplica global scope automático.
 * shouldBeSearchable() já filtra por business_id presente — alinhado com scope.
 *
 * LogsActivity (Wave 27): evidencias (screenshots/logs/snippets ingeridos)
 * podem conter info sensivel/contextual — auditar quem mudou status/triagem
 * preserva rastreabilidade governance + LGPD.
 */
class DocEvidence extends Model
{
    use HasBusinessScope;
    use LogsActivity;
    use Searchable;

    /**
     * Wave 27 — audit trail LGPD pra triagem de evidencias.
     *
     * Evidencias passam por ciclo: ingerida → triaged → linked-to-requirement.
     * Auditar mudancas (status/triaged_by/notes) preserva rastreabilidade
     * governance + LGPD (Wagner pode tirar duvida "quem triaged isso?").
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'docs_evidences';
    protected $guarded = ['id'];

    protected $casts = [
        'extracted_by_ai' => 'boolean',
        'ai_confidence'   => 'float',
        'triaged_at'      => 'datetime',
    ];

    /**
     * Campos indexados pelo Scout (ADR arq/0006).
     * Com driver `database` vira busca fulltext MySQL; com `meilisearch`
     * vira index remoto com highlight e facets.
     */
    public function toSearchableArray(): array
    {
        return [
            'id'               => (int) $this->id,
            'business_id'      => (int) $this->business_id,
            'kind'             => (string) ($this->kind ?? ''),
            'status'           => (string) ($this->status ?? ''),
            'module_target'    => (string) ($this->module_target ?? ''),
            'content'          => (string) ($this->content ?? ''),
            'notes'            => (string) ($this->notes ?? ''),
            'suggested_story_id' => (string) ($this->suggested_story_id ?? ''),
            'suggested_rule_id'  => (string) ($this->suggested_rule_id ?? ''),
        ];
    }

    /**
     * Só indexa se foi persistido com business_id setado.
     * (evita lixo em drafts temporários).
     */
    public function shouldBeSearchable(): bool
    {
        return ! empty($this->business_id);
    }

    public function source()
    {
        return $this->belongsTo(DocSource::class, 'source_id');
    }

    public function links()
    {
        return $this->hasMany(DocLink::class, 'evidence_id');
    }

    public function requirements()
    {
        return $this->belongsToMany(
            DocRequirement::class,
            'docs_links',
            'evidence_id',
            'requirement_id'
        )->withPivot('role')->withTimestamps();
    }

    public function triager()
    {
        return $this->belongsTo(\App\User::class, 'triaged_by');
    }
}
