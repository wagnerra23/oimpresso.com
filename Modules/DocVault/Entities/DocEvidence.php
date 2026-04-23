<?php

namespace Modules\DocVault\Entities;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class DocEvidence extends Model
{
    use Searchable;

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
