<?php

declare(strict_types=1);

namespace Modules\Governance\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Wave 28 Agent 1 (2026-05-17) — Initiative governance (Cortex/Port.io-style).
 *
 * Representa "scorecard breach → auto-task com deadline". Cada Initiative
 * é criada quando uma rule do scorecard cai abaixo do peso target — fica
 * `open` até score_after ≥ target (vira `done`), ou até deadline passar
 * (vira `expired` + alerta).
 *
 * Cross-tenant: sem business_id. Tabela mcp_governance_initiatives é
 * repo-wide (governance avalia código, não dados de negócio). Read-only
 * pra usuários normais via Governance dashboard; superadmin (business_id=1
 * Wagner) cria/edita/cancela manualmente quando necessário.
 *
 * @property int $id
 * @property string $module
 * @property string $bucket
 * @property string $rule_id
 * @property string $titulo
 * @property string $descricao
 * @property string $status open|in_progress|done|expired|cancelled
 * @property \Carbon\Carbon $deadline
 * @property int $score_before
 * @property int $score_target
 * @property int|null $score_after
 * @property int|null $owner_user_id
 * @property \Carbon\Carbon $opened_at
 * @property \Carbon\Carbon|null $closed_at
 * @property array|null $metadata
 *
 * @see Modules\Governance\Services\InitiativeService
 * @see Modules\Governance\Console\Commands\ScorecardInitiativeSyncCommand
 */
class Initiative extends Model
{
    protected $table = 'mcp_governance_initiatives';

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES_OPEN_LIKE = [self::STATUS_OPEN, self::STATUS_IN_PROGRESS];
    public const STATUSES_CLOSED = [self::STATUS_DONE, self::STATUS_EXPIRED, self::STATUS_CANCELLED];

    protected $fillable = [
        'module',
        'bucket',
        'rule_id',
        'titulo',
        'descricao',
        'status',
        'deadline',
        'score_before',
        'score_target',
        'score_after',
        'owner_user_id',
        'opened_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'deadline' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
        'score_before' => 'integer',
        'score_target' => 'integer',
        'score_after' => 'integer',
        'owner_user_id' => 'integer',
    ];

    public function isOpen(): bool
    {
        return in_array($this->status, self::STATUSES_OPEN_LIKE, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::STATUSES_CLOSED, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->deadline !== null && $this->deadline->isPast();
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::STATUSES_OPEN_LIKE);
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeForBucket($query, string $bucket)
    {
        return $query->where('bucket', $bucket);
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', self::STATUSES_OPEN_LIKE)
            ->where('deadline', '<', now()->toDateString());
    }
}
