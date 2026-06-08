<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ADR 0070 — Jira-style task management.
 *
 * REPO-WIDE: ADR 0070 jira-style cross-tenant intencional — epics agrupam tasks
 * repo-wide. Sem `business_id` by design. Wave 25 SATURATION marker explícito
 * pra rubrica D1.c v3.2 hardened.
 *
 * D7 LGPD audit trail — Wave 17 (2026-05-16): LogsActivity rastreia mudanças
 * de status/owner/target_quarter — essenciais pra timeline de Epic.
 *
 * @property int     $id
 * @property int     $project_id
 * @property string  $key             ex: COPI-EP-001
 * @property string  $title
 * @property ?string $description
 * @property ?string $owner
 * @property ?string $target_quarter
 * @property string  $status          planning|active|done|cancelled
 */
class McpEpic extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'mcp_epics';

    protected $fillable = [
        'project_id', 'key', 'title', 'description', 'owner',
        'target_quarter', 'status', 'color', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'int',
    ];

    public const STATUSES = ['planning', 'active', 'done', 'cancelled'];

    public function project()
    {
        return $this->belongsTo(McpProject::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(McpTask::class, 'epic_id');
    }

    public function scopeProject($q, ?int $projectId)
    {
        return $projectId ? $q->where('project_id', $projectId) : $q;
    }

    public function scopeStatus($q, ?string $status)
    {
        return $status ? $q->where('status', $status) : $q;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('mcp_epic')
            ->logOnly(['status', 'owner', 'target_quarter', 'title'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
