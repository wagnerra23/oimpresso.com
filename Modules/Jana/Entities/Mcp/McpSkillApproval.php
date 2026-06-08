<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0076 — registro de approval (approve/reject/request_changes).
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 16: tenancy herdada via CHAIN 2-level
 * version -> skill (mcp_skills.business_id). McpSkillVersion já tem
 * BelongsToBusinessViaParent (Wave 15), entao whereHas('version', ...) do
 * ScopeByBusinessViaParent cascateia o filtro automaticamente: a query
 * gerada vira whereHas('version', q.whereHas('skill', q.where(biz))).
 *
 * Skills com business_id NULL (plataforma) so aparecem pra superadmin
 * (mesmo comportamento da chain 1-level Wave 15).
 */
class McpSkillApproval extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'mcp_skill_approvals';

    /** Relacao parent que carrega business_id (via chain version -> skill). */
    protected string $businessParentRelation = 'version';

    protected $fillable = [
        'version_id', 'approver_id', 'decision', 'comment',
        'decided_at', 'test_runs_count', 'test_runs_pass',
    ];

    protected $casts = [
        'decided_at'      => 'datetime',
        'test_runs_count' => 'integer',
        'test_runs_pass'  => 'integer',
    ];

    public $timestamps = false;

    public function version(): BelongsTo
    {
        return $this->belongsTo(McpSkillVersion::class, 'version_id');
    }
}
