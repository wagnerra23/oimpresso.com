<?php

namespace Modules\SRS\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Wave 12 — Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 *
 * Tabela `docs_requirements` tem coluna `business_id` (migration
 * 2026_04_22_000003). Trait `HasBusinessScope` aplica global scope automático —
 * antes column-level (Controller-side), agora Model-level (Eloquent). Defense in depth.
 */
class DocRequirement extends Model
{
    use HasBusinessScope;

    protected $table = 'docs_requirements';
    protected $guarded = ['id'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

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
