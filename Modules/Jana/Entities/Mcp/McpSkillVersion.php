<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0076 — versão append-only de skill.
 */
class McpSkillVersion extends Model
{
    protected $table = 'mcp_skill_versions';

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
