<?php

declare(strict_types=1);

namespace Modules\Jana\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * US-COPI-099 — narrativa horária do Cockpit Saúde.
 *
 * Sem `business_id` — plataforma toda. Leitura é superadmin-only (ADR 0094 §5).
 *
 * @property int $id
 * @property \Carbon\Carbon $generated_at
 * @property string $severity
 * @property string $narrative
 * @property string $snapshot_hash
 * @property string $model
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property float|null $custo_brl
 * @property array|null $payload_summary
 */
class HealthNarrative extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'jana_health_narratives';

    protected $fillable = [
        'generated_at',
        'severity',
        'narrative',
        'snapshot_hash',
        'model',
        'tokens_in',
        'tokens_out',
        'custo_brl',
        'payload_summary',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'custo_brl' => 'float',
        'payload_summary' => 'array',
    ];

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }
}
