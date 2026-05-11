<?php

namespace Modules\OficinaAuto\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ServiceOrder — Ordem de Serviço da oficina automotiva.
 *
 * Schema ADR 0137 §"Escopo arquitetural V0":
 * - 1 OS = 1 venda (transaction_id FK UltimatePOS transactions, nullable durante draft)
 * - status: string livre na V0; FSM canônica chega em US-OFICINA-003 (ADR 0129)
 *   - OS Simples (Martinho): aberta → em_servico → concluida
 *   - OS Complexa (Vargas, V1): aberta → orcamento → aprovada → em_producao → concluida → entregue
 *
 * Multi-tenant Tier 0 (ADR 0093): global scope obrigatório.
 *
 * @property int         $id
 * @property int         $business_id
 * @property int|null    $transaction_id
 * @property int         $vehicle_id
 * @property int|null    $mileage_at_service
 * @property string      $status
 * @property \Illuminate\Support\Carbon|null $entered_at
 * @property \Illuminate\Support\Carbon|null $expected_completion
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property string|null $notes
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class ServiceOrder extends Model
{
    use SoftDeletes;

    protected $table = 'service_orders';

    protected $fillable = [
        'business_id',
        'transaction_id',
        'vehicle_id',
        'mileage_at_service',
        'status',
        'entered_at',
        'expected_completion',
        'completed_at',
        'delivered_at',
        'notes',
    ];

    protected $casts = [
        'business_id'           => 'integer',
        'transaction_id'        => 'integer',
        'vehicle_id'            => 'integer',
        'mileage_at_service'    => 'integer',
        'entered_at'            => 'datetime',
        'expected_completion'   => 'datetime',
        'completed_at'          => 'datetime',
        'delivered_at'          => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('service_orders.business_id', $businessId);
            }
        });

        static::creating(function (ServiceOrder $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * Transaction UltimatePOS (Sells). Pode ser null durante draft.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }
}
