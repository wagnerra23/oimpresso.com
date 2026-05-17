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
 *
 * REPO-WIDE: ADR 0070 jira-style cross-tenant intencional — planejamento
 * é da plataforma, não per-business. Sem `business_id` by design (governança
 * MCP gated por Spatie permission `copiloto.mcp.tasks.*`). Wave 25 SATURATION
 * marker explícito pra rubrica D1.c v3.2 hardened.
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
