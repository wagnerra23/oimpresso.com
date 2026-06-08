<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0076 — labels móveis (production/staging/dev) — Langfuse-style.
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 15: tenancy herdada via parent `skill`
 * (mcp_skills.business_id) — label production/staging só do tenant.
 */
class McpSkillLabel extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'mcp_skill_labels';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'skill';

    protected $fillable = [
        'skill_id', 'label', 'version_id',
        'moved_by', 'moved_at', 'previous_version_id', 'reason',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public $timestamps = false; // tem moved_at customizado

    public function skill(): BelongsTo
    {
        return $this->belongsTo(McpSkill::class, 'skill_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(McpSkillVersion::class, 'version_id');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(McpSkillVersion::class, 'previous_version_id');
    }
}
