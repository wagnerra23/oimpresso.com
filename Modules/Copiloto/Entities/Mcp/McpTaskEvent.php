<?php

declare(strict_types=1);

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * TaskRegistry Fase 1 (US-TR-006) — evento de audit por task.
 *
 * Append-only: nunca atualizar ou deletar registros desta tabela.
 *
 * @property int         $id
 * @property string      $task_id
 * @property string      $event_type
 * @property string|null $from_value
 * @property string|null $to_value
 * @property string|null $author
 * @property string|null $note
 * @property \Carbon\Carbon $occurred_at
 */
class McpTaskEvent extends Model
{
    protected $table = 'mcp_task_events';

    public $timestamps = false;

    protected $fillable = [
        'task_id', 'event_type', 'from_value', 'to_value',
        'author', 'note', 'occurred_at', 'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public static function log(
        string $taskId,
        string $eventType,
        ?string $from = null,
        ?string $to = null,
        ?string $author = 'system',
        ?string $note = null,
    ): self {
        return self::create([
            'task_id'    => $taskId,
            'event_type' => $eventType,
            'from_value' => $from,
            'to_value'   => $to,
            'author'     => $author,
            'note'       => $note,
            'created_at' => now(),
        ]);
    }
}
