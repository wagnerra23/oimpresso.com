<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0076 — test run inline contra inputs reais multi-tenant.
 */
class McpSkillTestRun extends Model
{
    protected $table = 'mcp_skill_test_runs';

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

    public $timestamps = false; // só executed_at

    public function version(): BelongsTo
    {
        return $this->belongsTo(McpSkillVersion::class, 'version_id');
    }
}
