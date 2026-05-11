<?php

namespace Modules\OficinaAuto\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vehicle — veículo da oficina (cliente/frota).
 *
 * Schema ADR 0137 §"Escopo arquitetural V0":
 * - PLACA principal obrigatória (Mercosul ou antiga) — validação no Controller
 * - PLACA_SECUNDARIA nullable atende cavalo+reboque (Vargas case, 20% dos veículos)
 * - vehicle_type ENUM cobre 3 sub-verticais (CNAEs 4520/2212/4581)
 * - legacy_id preserva CODIGO Firebird pra importer US-OFICINA-002
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório — filtra por business_id da sessão automaticamente.
 *
 * @property int         $id
 * @property int         $business_id
 * @property int|null    $contact_id
 * @property string      $plate
 * @property string|null $secondary_plate
 * @property string|null $chassis
 * @property string|null $secondary_chassis
 * @property int|null    $manufacture_year
 * @property int|null    $model_year
 * @property string|null $renavam
 * @property string      $vehicle_type
 * @property string|null $engine
 * @property int|null    $mileage_at_entry
 * @property string|null $fuel_type
 * @property string|null $color
 * @property string|null $notes
 * @property string|null $legacy_id
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class Vehicle extends Model
{
    use SoftDeletes;

    protected $table = 'vehicles';

    protected $fillable = [
        'business_id',
        'contact_id',
        'plate',
        'secondary_plate',
        'chassis',
        'secondary_chassis',
        'manufacture_year',
        'model_year',
        'renavam',
        'vehicle_type',
        'engine',
        'mileage_at_entry',
        'fuel_type',
        'color',
        'notes',
        'legacy_id',
    ];

    protected $casts = [
        'business_id'       => 'integer',
        'contact_id'        => 'integer',
        'manufacture_year'  => 'integer',
        'model_year'        => 'integer',
        'mileage_at_entry'  => 'integer',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('vehicles.business_id', $businessId);
            }
        });

        static::creating(function (Vehicle $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Dono do veículo (contact UltimatePOS — cliente).
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    /**
     * Histórico de OS deste veículo.
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'vehicle_id');
    }
}
