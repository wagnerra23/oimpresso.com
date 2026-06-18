<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ErrorGroup — grupo de erros deduplicado por dedupKey (Fase 2 · E-2).
 *
 * Tabela de PLATAFORMA (governança repo-wide). NÃO usa HasBusinessScope de
 * propósito: o `dedup_key` já carrega o business afetado e a leitura é
 * cross-tenant — mesma natureza de `mcp_audit_log`. @see ErrorGrouper.
 *
 * @see prototipo-ui/handoffs/erros-dedup.md
 *
 * @property int $count
 * @property string $status
 * @property array<string,mixed>|null $sample_payload
 */
class ErrorGroup extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_MUTED = 'muted';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'error_groups';

    protected $guarded = ['id'];

    protected $casts = [
        'count'          => 'integer',
        'first_seen'     => 'datetime',
        'last_seen'      => 'datetime',
        'sample_payload' => 'array',
    ];

    /** Grupos abertos sem ocorrência há $days dias — candidatos a arquivar. */
    public function scopeStale(Builder $query, int $days): Builder
    {
        return $query->where('status', self::STATUS_OPEN)
            ->where('last_seen', '<', now()->subDays($days));
    }
}
