<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0076 — test run inline contra inputs reais multi-tenant.
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 16: tenancy herdada via CHAIN 2-level
 * version -> skill (mcp_skills.business_id). A coluna business_id_scope
 * existe no schema apenas pra anotar QUAL biz forneceu o input real (audit
 * LGPD), NAO substitui o scope global. McpSkillVersion ja tem
 * BelongsToBusinessViaParent (Wave 15), entao o whereHas('version', ...)
 * do ScopeByBusinessViaParent cascateia ate skill.business_id.
 */
class McpSkillTestRun extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'mcp_skill_test_runs';

    /** Relacao parent que carrega business_id (via chain version -> skill). */
    protected string $businessParentRelation = 'version';

    protected $fillable = [
        'version_id', 'input_source', 'input_json',
        'output', 'output_tokens', 'latency_ms',
        'business_id_scope', 'pii_redactions_count',
        'passed', 'pass_reason',
        'executed_by', 'executed_at',
    ];

    protected $casts = [
        'input_json'          => 'array',
        'output_tokens'       => 'integer',
        'latency_ms'          => 'integer',
        'pii_redactions_count'=> 'integer',
        'passed'              => 'boolean',
        'executed_at'         => 'datetime',
    ];

    public $timestamps = false; // so executed_at

    public function version(): BelongsTo
    {
        return $this->belongsTo(McpSkillVersion::class, 'version_id');
    }
}
