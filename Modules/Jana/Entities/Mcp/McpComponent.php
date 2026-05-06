<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Component = agrupamento ortogonal a Epic.
 * Ex: Frontend, Backend, Infra, Memória, Tests.
 */
class McpComponent extends Model
{
    use SoftDeletes;

    protected $table = 'mcp_components';

    protected $fillable = [
        'project_id', 'key', 'name', 'description',
        'lead_user_id', 'color',
    ];

    public function project()
    {
        return $this->belongsTo(McpProject::class, 'project_id');
    }

    public function tasks()
    {
        return $this->hasMany(McpTask::class, 'component_id');
    }
}
