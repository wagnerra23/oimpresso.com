<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ADR 0070 + ADR 0100 — Watchers de tasks Jira-style.
 *
 * Permite que users sigam uma task sem ser owner/assignee. Receberão
 * notificações de status_changed/commented/etc via mcp_inbox_notifications.
 *
 * REPO-WIDE: ADR 0070 jira-style cross-tenant intencional — subscription
 * per-user em tasks repo-wide. Sem `business_id` by design (isolamento via
 * user_id). Wave 25 SATURATION marker explícito pra rubrica D1.c v3.2 hardened.
 *
 * Migration: Modules/Jana/Database/Migrations/2026_05_04_180011_create_mcp_task_watchers_table.php
 *   - id (bigIncrements)
 *   - task_id (string 40, FK lógica pra mcp_tasks.task_id)
 *   - user_id (unsignedBigInteger, FK lógica pra users.id)
 *   - timestamps
 *   - unique(task_id, user_id)
 *
 * @property int        $id
 * @property string     $task_id
 * @property int        $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class McpTaskWatcher extends Model
{
    protected $table = 'mcp_task_watchers';

    protected $fillable = ['task_id', 'user_id'];

    protected $casts = [
        'user_id' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
