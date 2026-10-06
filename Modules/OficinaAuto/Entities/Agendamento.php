<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agendamento de revisão da Oficina (decisão [W] 2026-10-06): veículo + cliente sugerido + dia e
 * hora + observação. `atendido` quando a OS é aberta a partir dele (os_id); `cancelado` com motivo.
 *
 * Multi-tenant Tier 0 (ADR 0093): global scope por business_id da sessão. A API do app (token, sem
 * sessão) consulta com business_id explícito — o escopo daqui não cobre aquele caminho.
 *
 * @property int $id
 * @property int $business_id
 * @property int $vehicle_id
 * @property int|null $contact_id
 * @property \Illuminate\Support\Carbon $inicio
 * @property string|null $observacao
 * @property string $status
 * @property int|null $os_id
 * @property string|null $motivo_cancelamento
 * @property int|null $created_by
 */
class Agendamento extends Model
{
    public const AGENDADO = 'agendado';

    public const ATENDIDO = 'atendido';

    public const CANCELADO = 'cancelado';

    protected $table = 'oficina_agendamentos';

    protected $fillable = [
        'business_id',
        'vehicle_id',
        'contact_id',
        'inicio',
        'observacao',
        'status',
        'os_id',
        'motivo_cancelamento',
        'created_by',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'vehicle_id' => 'integer',
        'contact_id' => 'integer',
        'inicio' => 'datetime',
        'os_id' => 'integer',
        'created_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('oficina_agendamentos.business_id', $businessId);
            }
        });

        static::creating(function (Agendamento $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'os_id');
    }
}
