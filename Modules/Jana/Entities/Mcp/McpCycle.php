<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Cycle = sprint de duração fixa com goal outcome-oriented.
 * 1 cycle ativo por projeto (status='active').
 *
 * REPO-WIDE: ADR 0070 jira-style cross-tenant intencional — sprints da
 * plataforma, não per-business. Sem `business_id` by design. Wave 25
 * SATURATION marker explícito pra rubrica D1.c v3.2 hardened.
 *
 * D7 LGPD audit trail — Wave 17 (2026-05-16): LogsActivity rastreia mudanças
 * de status/goal/datas — pra reconstruir timeline de sprints (retro audit).
 *
 * @property int     $id
 * @property int     $project_id
 * @property string  $key             ex: CYCLE-01
 * @property ?string $name
 * @property Carbon  $start_date
 * @property Carbon  $end_date
 * @property ?string $goal
 * @property string  $status          planning|active|closed
 * @property ?array  $retro
 */
class McpCycle extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'mcp_cycles';

    protected $fillable = [
        'project_id', 'key', 'name', 'start_date', 'end_date',
        'goal', 'status', 'retro', 'owner_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'retro' => 'array',
    ];

    public const STATUSES = ['planning', 'active', 'closed'];

    public function project()
    {
        return $this->belongsTo(McpProject::class, 'project_id');
    }

    public function goals()
    {
        return $this->hasMany(McpCycleGoal::class, 'cycle_id')->orderBy('sort_order');
    }

    public function tasks()
    {
        return $this->hasMany(McpTask::class, 'cycle_id');
    }

    public function scopeProject($q, ?int $projectId)
    {
        return $projectId ? $q->where('project_id', $projectId) : $q;
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function daysRemaining(): int
    {
        return (int) max(0, today()->diffInDays($this->end_date, false));
    }

    public function progressPercent(): float
    {
        $total = (float) ($this->start_date->diffInDays($this->end_date) ?: 1);
        $elapsed = (float) $this->start_date->diffInDays(today());
        return min(100.0, max(0.0, ($elapsed / $total) * 100));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('mcp_cycle')
            ->logOnly(['status', 'goal', 'start_date', 'end_date', 'owner_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
