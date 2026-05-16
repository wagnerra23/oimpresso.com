<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0076 — versão append-only de skill.
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 15: tenancy herdada via parent `skill`
 * (mcp_skills.business_id). Skills com business_id NULL (plataforma) só
 * aparecem pra superadmin (ScopeByBusinessViaParent respeita).
 */
class McpSkillVersion extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'mcp_skill_versions';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'skill';

    protected $fillable = [
        'skill_id', 'version',
        'body_markdown', 'frontmatter_json',
        'rationale_problem', 'rationale_hypothesis',
        'rationale_success_metric', 'rationale_rollback',
        'origin', 'status',
        'git_sha', 'pr_number', 'published_to_git_at',
        'pii_redactions_count', 'created_by',
    ];

    protected $casts = [
        'frontmatter_json'      => 'array',
        'pr_number'             => 'integer',
        'pii_redactions_count'  => 'integer',
        'published_to_git_at'   => 'datetime',
    ];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(McpSkill::class, 'skill_id');
    }
}
