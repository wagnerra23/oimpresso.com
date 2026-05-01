<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * TaskRegistry Fase 0 (ADR TaskRegistry/0001).
 *
 * US-* extraídas de memory/requisitos/<Mod>/SPEC.md.
 * Cache governado, source-of-truth permanece em git.
 */
class McpTask extends Model
{
    protected $table = 'mcp_tasks';

    protected $fillable = [
        'task_id',
        'module',
        'title',
        'description',
        'status',
        'owner',
        'sprint',
        'priority',
        'estimate_h',
        'blocked_by',
        'source_path',
        'source_git_sha',
        'parsed_at',
    ];

    protected $casts = [
        'blocked_by' => 'array',
        'estimate_h' => 'decimal:1',
        'parsed_at' => 'datetime',
    ];

    /** Status canônicos. */
    public const STATUSES = ['todo', 'doing', 'review', 'done', 'blocked', 'cancelled'];

    /** Priorities canônicas. */
    public const PRIORITIES = ['p0', 'p1', 'p2', 'p3'];

    public function scopeOwner($query, ?string $owner)
    {
        if ($owner === null || $owner === '') {
            return $query;
        }
        return $query->where('owner', $owner);
    }

    public function scopeModule($query, ?string $module)
    {
        if ($module === null || $module === '') {
            return $query;
        }
        return $query->where('module', $module);
    }

    public function scopeStatus($query, ?string $status)
    {
        if ($status === null || $status === '') {
            return $query;
        }
        return $query->where('status', $status);
    }

    public function scopeSprint($query, ?string $sprint)
    {
        if ($sprint === null || $sprint === '') {
            return $query;
        }
        return $query->where('sprint', $sprint);
    }

    public function scopePriority($query, ?string $priority)
    {
        if ($priority === null || $priority === '') {
            return $query;
        }
        return $query->where('priority', $priority);
    }
}
