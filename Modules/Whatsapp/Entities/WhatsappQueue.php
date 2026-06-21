<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * WhatsappQueue — fila de atendimento persistida (US-WA-301 · ADR 0267).
 *
 * Multi-tenant Tier 0 (ADR 0093) via trait `HasBusinessScope`.
 *
 * Substitui `config('whatsapp.queues')` estático. Seed lazy idempotente a
 * partir do config no primeiro acesso por business
 * (`CaixaUnificadaController::ensureDefaultQueues`).
 *
 * `dist` e `members` são SÓ persistência nesta fase — roteamento automático
 * (round-robin/sticky) é US futura (TODO honesto anti M-AP-2).
 *
 * @property int $id
 * @property int $business_id
 * @property string $slug
 * @property string $label
 * @property int $hue
 * @property ?int $sla_minutes
 * @property string $dist
 * @property array $trigger_tags
 * @property array $members
 * @property int $sort_order
 */
class WhatsappQueue extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_queues';

    public const DIST_MODES = ['round_robin', 'sticky', 'manual'];

    protected $fillable = [
        'business_id', 'slug', 'label', 'hue', 'sla_minutes',
        'dist', 'trigger_tags', 'members', 'sort_order',
    ];

    protected $casts = [
        'hue' => 'integer',
        'sla_minutes' => 'integer',
        'trigger_tags' => 'array',
        'members' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * SLA humanizado pro shape `QueueConfig` do frontend ("45min", "1h", "4h").
     */
    public function slaHuman(): ?string
    {
        if ($this->sla_minutes === null || $this->sla_minutes <= 0) {
            return null;
        }
        if ($this->sla_minutes < 60) {
            return "{$this->sla_minutes}min";
        }
        $hours = intdiv($this->sla_minutes, 60);
        $rest = $this->sla_minutes % 60;

        return $rest === 0 ? "{$hours}h" : "{$hours}h{$rest}";
    }

    /**
     * Converte SLA humano do config ("1h", "4h", "30min") pra minutos.
     * Aceita também int/numeric-string (minutos diretos).
     */
    public static function slaToMinutes(mixed $sla): ?int
    {
        if ($sla === null || $sla === '') {
            return null;
        }
        if (is_int($sla) || (is_string($sla) && ctype_digit($sla))) {
            return (int) $sla;
        }
        if (is_string($sla) && preg_match('/^(?:(\d+)h)?(?:(\d+)(?:min)?)?$/', trim($sla), $m)) {
            $minutes = ((int) ($m[1] ?? 0)) * 60 + (int) ($m[2] ?? 0);

            return $minutes > 0 ? $minutes : null;
        }

        return null;
    }
}
