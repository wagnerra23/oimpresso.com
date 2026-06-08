<?php

declare(strict_types=1);

namespace Modules\Jana\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * US-COPI-099 — narrativa horária do Cockpit Saúde.
 *
 * Sem `business_id` — plataforma toda. Leitura é superadmin-only (ADR 0094 §5).
 *
 * D7 LGPD audit trail — Wave 10 (2026-05-16): LogsActivity registra severidade
 * + mudanças de modelo/custo. NÃO loga `narrative` (texto livre que pode
 * conter referências a clientes). Retention 730d (Config/retention.php).
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
    use LogsActivity;

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

    /**
     * D7 LGPD audit — registra severidade + identidade (model/snapshot_hash).
     * NÃO loga `narrative` (texto livre PII-relevante — pode citar nomes de
     * cliente ou business inativos).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('jana_health_narrative')
            ->logOnly(['severity', 'model', 'snapshot_hash', 'custo_brl'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }
}
