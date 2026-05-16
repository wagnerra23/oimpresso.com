<?php

namespace Modules\OficinaAuto\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Vehicle — veículo da oficina (cliente/frota) ou caçamba estacionária (Martinho).
 *
 * Schema ADR 0137 §"Escopo arquitetural V0":
 * - PLACA principal obrigatória (Mercosul ou antiga) — validação no Controller
 * - PLACA_SECUNDARIA nullable atende cavalo+reboque (Vargas case, 20% dos veículos)
 * - vehicle_type ENUM cobre 3 sub-verticais (CNAEs 4520/2212/4581)
 * - legacy_id preserva CODIGO Firebird pra importer US-OFICINA-002
 *
 * Caçamba avulsa (extension migration 2026_05_12_220001):
 * - capacity_m3 + current_status + current_rental_id pra fluxo locação Martinho
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório — filtra por business_id da sessão automaticamente.
 *
 * @property int          $id
 * @property int          $business_id
 * @property int|null     $contact_id
 * @property string       $plate
 * @property string|null  $secondary_plate
 * @property string|null  $chassis
 * @property string|null  $secondary_chassis
 * @property int|null     $manufacture_year
 * @property int|null     $model_year
 * @property string|null  $renavam
 * @property string       $vehicle_type
 * @property string|null  $capacity_m3
 * @property string       $current_status
 * @property int|null     $current_rental_id
 * @property string|null  $engine
 * @property int|null     $mileage_at_entry
 * @property string|null  $fuel_type
 * @property string|null  $color
 * @property string|null  $notes
 * @property string|null  $legacy_id
 *
 * @property-read string  $status_badge_color   Accessor — cor do badge UI (emerald | blue | amber | rose | slate)
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class Vehicle extends Model
{
    use LogsActivity; // D7.b LGPD audit trail (Wave 14)
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
        'capacity_m3',
        'current_status',
        'current_rental_id',
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
        'capacity_m3'       => 'decimal:2',
        'current_rental_id' => 'integer',
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
     * Histórico de OS deste veículo (locações + manutenções).
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'vehicle_id');
    }

    /**
     * Locação atualmente ativa (FK soft via current_rental_id) —
     * facilita listagem sem nested query (caçambas disponíveis × locadas).
     */
    public function currentRental(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'current_rental_id');
    }

    /**
     * Histórico de locações (apenas order_type=locacao).
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'vehicle_id')
                    ->where('order_type', 'locacao');
    }

    /**
     * Histórico de manutenções (apenas order_type=manutencao).
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'vehicle_id')
                    ->where('order_type', 'manutencao');
    }

    // ------------------------------------------------------------------
    // Accessors (UI helpers)
    // ------------------------------------------------------------------

    /**
     * Cor do badge UI baseado em current_status + flag is_overdue da locação ativa.
     *
     * - emerald  → disponivel
     * - blue     → locada (no prazo)
     * - rose     → locada + atrasada (current_rental.is_overdue)
     * - amber    → manutencao
     * - slate    → indisponivel (qualquer outro estado)
     */
    public function getStatusBadgeColorAttribute(): string
    {
        $status = $this->current_status ?? 'indisponivel';

        if ($status === 'disponivel') {
            return 'emerald';
        }

        if ($status === 'locada') {
            // Se rental ativa está atrasada — sinaliza vermelho/rose
            $rental = $this->relationLoaded('currentRental') ? $this->currentRental : null;
            if ($rental && $rental->is_overdue) {
                return 'rose';
            }
            return 'blue';
        }

        if ($status === 'manutencao') {
            return 'amber';
        }

        return 'slate';
    }

    // ------------------------------------------------------------------
    // LGPD audit trail (D7.b — Wave 14)
    // ------------------------------------------------------------------

    /**
     * Spatie ActivityLog — registra mudanças em campos PII-relevantes
     * (placa, RENAVAM, chassi, contact_id) com `logOnlyDirty`. Audit trail
     * append-only conforme retention.php contrato.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'plate',
                'secondary_plate',
                'chassis',
                'renavam',
                'contact_id',
                'current_status',
                'vehicle_type',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('oficinaauto.vehicle');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Veículos com locação ativa em atraso (expected_return_date passou).
     *
     * Join com service_orders pra detectar OS locação não-concluída onde
     * expected_return_date < hoje.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereExists(function ($sub) {
            $sub->select(\DB::raw(1))
                ->from('service_orders')
                ->whereColumn('service_orders.vehicle_id', 'vehicles.id')
                ->where('service_orders.order_type', 'locacao')
                ->whereNotIn('service_orders.status', ['concluida', 'cancelada', 'recolhida'])
                ->whereNotNull('service_orders.expected_return_date')
                ->whereDate('service_orders.expected_return_date', '<', now()->toDateString())
                ->whereNull('service_orders.deleted_at');
        });
    }
}
