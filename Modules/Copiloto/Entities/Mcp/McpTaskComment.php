<?php

declare(strict_types=1);

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * TaskRegistry Fase 1 (US-TR-006) — comentário em uma task.
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
