<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TaskRegistry estendida para Jira-style (ADR 0070, supersedes ADR 0069).
 *
 * Hierarquia: Project → Epic → Cycle → Story → Subtask + Component cross-cut.
 * Source-of-truth de US-XXX-NNN: memory/requisitos/<Mod>/SPEC.md (parser sync).
 * Tasks ad-hoc (sem entry no SPEC) podem ser criadas via tasks-create direto.
 *
 * Identifier humano (Linear-style): "<PROJECT_KEY>-<NNNN>", ex: COPI-123.
 * Mantém task_id legacy (US-XXX-NNN) durante migração.
 *
 * D7 LGPD audit trail — Wave 17 (2026-05-16): LogsActivity registra mudanças
 * estruturais (status, owner, priority, cycle_id, epic_id) — essenciais pra
 * auditoria de governança (ADR 0070 Jira-style). Tabela mcp_tasks é repo-wide
 * (sem business_id) — gated por Spatie permission `copiloto.mcp.tasks.*`.
 */
class McpTask extends Model
{
    use LogsActivity;

    protected $table = 'mcp_tasks';

    protected $fillable = [
        'task_id',
        'identifier',
        'project_id',
        'epic_id',
        'cycle_id',
        'component_id',
        'parent_task_id',
        'module',
        'title',
        'description',
        'status',
        'type',
        'owner',
        'sprint',
        'priority',
        'estimate_h',
        'story_points',
        'estimate_unit',
        'estimate_value',
        'due_date',
        'started_at',
        'completed_at',
        'labels',
        'custom_fields',
        'blocked_by',
        'source_path',
        'source_git_sha',
        'parsed_at',
    ];

    protected $casts = [
        'project_id' => 'int',
        'epic_id' => 'int',
        'cycle_id' => 'int',
        'component_id' => 'int',
        'parent_task_id' => 'int',
        'estimate_h' => 'decimal:1',
        'story_points' => 'decimal:1',
        'estimate_value' => 'decimal:2',
        'due_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'labels' => 'array',
        'custom_fields' => 'array',
        'blocked_by' => 'array',
        'parsed_at' => 'datetime',
    ];

    /** Status canônicos (ADR 0070 — backlog adicionado). */
    public const STATUSES = ['backlog', 'todo', 'doing', 'review', 'done', 'blocked', 'cancelled'];

    /** Priorities canônicas. */
    public const PRIORITIES = ['p0', 'p1', 'p2', 'p3'];

    /** Tipos de issue. */
    public const TYPES = ['story', 'task', 'bug', 'spike', 'chore', 'epic-stub'];

    /** Unidades de estimativa flexíveis. */
    public const ESTIMATE_UNITS = ['points', 'hours', 'days', 'tshirt', 'fibonacci'];

    public function project()
    {
        return $this->belongsTo(McpProject::class, 'project_id');
    }

    public function epic()
    {
        return $this->belongsTo(McpEpic::class, 'epic_id');
    }

    public function cycle()
    {
        return $this->belongsTo(McpCycle::class, 'cycle_id');
    }

    public function component()
    {
        return $this->belongsTo(McpComponent::class, 'component_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks()
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function comments()
    {
        return $this->hasMany(McpTaskComment::class, 'task_id', 'task_id');
    }

    public function events()
    {
        return $this->hasMany(McpTaskEvent::class, 'task_id', 'task_id');
    }

    public function dependencies()
    {
        return $this->hasMany(McpTaskDependency::class, 'task_id', 'task_id');
    }

    /** Tasks que esta bloqueia (reverse dependency). */
    public function blocks()
    {
        return $this->hasMany(McpTaskDependency::class, 'depends_on_task_id', 'task_id')
            ->where('type', 'blocks');
    }

    public function scopeOwner($query, ?string $owner)
    {
        return $owner ? $query->where('owner', $owner) : $query;
    }

    public function scopeModule($query, ?string $module)
    {
        return $module ? $query->where('module', $module) : $query;
    }

    public function scopeStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeSprint($query, ?string $sprint)
    {
        return $sprint ? $query->where('sprint', $sprint) : $query;
    }

    public function scopePriority($query, ?string $priority)
    {
        return $priority ? $query->where('priority', $priority) : $query;
    }

    public function scopeProject($query, ?int $projectId)
    {
        return $projectId ? $query->where('project_id', $projectId) : $query;
    }

    public function scopeEpic($query, ?int $epicId)
    {
        return $epicId ? $query->where('epic_id', $epicId) : $query;
    }

    public function scopeCycle($query, ?int $cycleId)
    {
        return $cycleId ? $query->where('cycle_id', $cycleId) : $query;
    }

    public function scopeComponent($query, ?int $componentId)
    {
        return $componentId ? $query->where('component_id', $componentId) : $query;
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['backlog', 'todo', 'doing', 'review', 'blocked']);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['todo', 'doing', 'review', 'blocked']);
    }

    public function scopeTriage($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('owner')->orWhereNull('priority')->orWhere('status', 'backlog');
        });
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done', 'cancelled']);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['done', 'cancelled'], true);
    }

    /** Identifier humano (Linear-style) ou fallback pro task_id legacy. */
    public function getDisplayIdAttribute(): string
    {
        return $this->identifier ?: $this->task_id;
    }

    /**
     * D7 LGPD audit — logga apenas campos estruturais críticos pra auditoria
     * de governança (ADR 0070 Jira-style). Sem PII direta (description/title
     * podem conter nomes, mas auditoria mantém estado, não conteúdo).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('mcp_task')
            ->logOnly(['status', 'owner', 'priority', 'cycle_id', 'epic_id', 'completed_at', 'blocked_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
