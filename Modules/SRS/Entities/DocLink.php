<?php

namespace Modules\SRS\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * DocLink — relacionamento N-N entre DocEvidence e DocRequirement.
 *
 * Wave 17 — Multi-tenant Tier 0 (ADR 0093) — EXCEÇÃO REPO-WIDE documentada.
 *
 * Tabela `docs_links` é pivot pura (evidence_id, requirement_id, role) — NÃO
 * tem coluna `business_id` por design (migration 2026_04_22_000004). Ambos os
 * lados (DocEvidence + DocRequirement) já têm `HasBusinessScope` aplicado, que
 * garante isolamento Tier 0 transitivamente: queries que vêm via
 * `$evidence->links` ou `$requirement->links` já estão escopadas pelo parent.
 *
 * Equivalente a `permissions`/`role_has_permissions` (Spatie) ou
 * `media_library` — pivot/repo-wide sem `business_id` próprio é pattern
 * aceito quando ambas pontas governam.
 *
 * NÃO aplicar HasBusinessScope direto: tabela sem coluna `business_id`
 * causaria "Unknown column 'docs_links.business_id'" em toda query.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §"Exceção repo-wide"
 */
class DocLink extends Model
{
    protected $table = 'docs_links';
    protected $guarded = ['id'];

    protected $casts = [
        'linked_at' => 'datetime',
    ];

    public function evidence()
    {
        return $this->belongsTo(DocEvidence::class, 'evidence_id');
    }

    public function requirement()
    {
        return $this->belongsTo(DocRequirement::class, 'requirement_id');
    }
}
