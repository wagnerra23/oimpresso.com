<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * TaskRegistry Fase 1 (US-TR-006) — comentário em uma task.
 *
 * REPO-WIDE: ADR 0070 jira-style cross-tenant intencional — herda task que
 * é repo-wide. Sem `business_id` by design (governança MCP gated por Spatie
 * permission `copiloto.mcp.tasks.*`). Wave 25 SATURATION marker explícito pra
 * rubrica D1.c v3.2 hardened.
 *
 * @property int    $id
 * @property string $task_id
 * @property string $author
 * @property string $body
 */
class McpTaskComment extends Model
{
    protected $table = 'mcp_task_comments';

    protected $fillable = ['task_id', 'author', 'body'];
}
