<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Substitui mcp_tasks.blocked_by JSON por estrutura relacional com tipos:
 * blocks, relates, duplicates, clones.
 *
 * REPO-WIDE: ADR 0070 jira-style cross-tenant intencional — relação entre tasks
 * repo-wide. Sem `business_id` by design. Wave 25 SATURATION marker explícito
 * pra rubrica D1.c v3.2 hardened.
 */
class McpTaskDependency extends Model
{
    protected $table = 'mcp_task_dependencies';

    protected $fillable = [
        'task_id', 'depends_on_task_id', 'type', 'created_by',
    ];

    public const TYPES = ['blocks', 'relates', 'duplicates', 'clones'];

    public function task()
    {
        return $this->belongsTo(McpTask::class, 'task_id', 'task_id');
    }

    public function dependsOnTask()
    {
        return $this->belongsTo(McpTask::class, 'depends_on_task_id', 'task_id');
    }

    public function scopeBlocking($q)
    {
        return $q->where('type', 'blocks');
    }
}
