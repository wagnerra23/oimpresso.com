<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0076 — registro de approval (approve/reject/request_changes).
 */
class McpSkillApproval extends Model
{
    protected $table = 'mcp_skill_approvals';

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
