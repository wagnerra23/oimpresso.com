<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ADR 0070 — Jira-style task management.
 *
 * REPO-WIDE: ADR 0070 jira-style cross-tenant intencional — goals herdam cycle
 * (planning repo-wide). Sem `business_id` by design. Wave 25 SATURATION marker
 * explícito pra rubrica D1.c v3.2 hardened.
 *
 * D7 LGPD audit trail — Wave 17 (2026-05-16): LogsActivity rastreia mudanças
 * de status/achieved_value/target_value — goals são prova outcome do sprint.
 *
 * @property int     $id
 * @property int     $cycle_id
 * @property string  $description
 * @property ?string $metric_name
 * @property ?string $target_value
 * @property ?string $achieved_value
 * @property string  $status          open|done|missed
 */
class McpCycleGoal extends Model
{
    use LogsActivity;

    protected $table = 'mcp_cycle_goals';

    protected $fillable = [
        'cycle_id', 'description', 'metric_name',
        'target_value', 'achieved_value', 'status', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'int',
    ];

    public const STATUSES = ['open', 'done', 'missed'];

    public function cycle()
    {
        return $this->belongsTo(McpCycle::class, 'cycle_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('mcp_cycle_goal')
            ->logOnly(['status', 'achieved_value', 'target_value', 'metric_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
