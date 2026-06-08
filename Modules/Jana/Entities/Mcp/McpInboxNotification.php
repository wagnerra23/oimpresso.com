<?php

declare(strict_types=1);

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Inbox unificado: @mention, assignment, review_requested, status_changed, commented.
 *
 * REPO-WIDE: ADR 0070 jira-style cross-tenant intencional — inbox per-user
 * (isolamento via user_id, não via business_id). Wave 25 SATURATION marker
 * explícito pra rubrica D1.c v3.2 hardened.
 */
class McpInboxNotification extends Model
{
    protected $table = 'mcp_inbox_notifications';

    protected $fillable = [
        'user_id', 'type', 'task_id', 'actor_id',
        'body', 'payload', 'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    public const TYPES = [
        'mention',
        'assigned',
        'review_requested',
        'status_changed',
        'commented',
        'due_soon',
        'blocked_resolved',
    ];

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at');
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->read_at = now();
            $this->save();
        }
    }

    public static function notify(
        int $userId,
        string $type,
        ?string $taskId = null,
        ?int $actorId = null,
        ?string $body = null,
        ?array $payload = null,
    ): self {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'task_id' => $taskId,
            'actor_id' => $actorId,
            'body' => $body,
            'payload' => $payload,
        ]);
    }
}
