<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * ADR 0070 — Jira-style task management.
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
}
