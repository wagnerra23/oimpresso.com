<?php

namespace Modules\OficinaAuto\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Arquivos\Concerns\HasArquivos;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ServiceOrder — Ordem de Serviço da oficina automotiva (mecânica pesada caminhão basculante · Martinho LIVE prod · sub-vertical 4 ADR 0194).
 *
 * Schema ADR 0137 §"Escopo arquitetural V0" (amendado por ADR 0194 2026-05-26):
 * - 1 OS = 1 venda (transaction_id FK UltimatePOS transactions, nullable durante draft)
 * - status: string livre na V0; FSM canônica chega em US-OFICINA-003 (ADR 0129)
 *   - OS Simples (Martinho · sub-vertical 4 mecânica pesada caminhão basculante): aberta → em_servico → concluida
 *   - OS Complexa (Vargas · sub-vertical 2 recapagem · V1): aberta → orcamento → aprovada → em_producao → concluida → entregue
 *
 * order_type extension migration 2026_05_12_220002 (schema preservado nullable pós-ADR 0194):
 * - order_type=manutencao → fluxo Martinho mecânica pesada (predominante prod biz=164) — sub-vertical 4 default
 * - order_type=locacao → schema sub-vertical 3 hipotético locação caçamba container (sem cliente real ancorado pós-ADR 0194 — pré-correção dizia "fluxo Martinho caçamba avulsa")
 *
 * Multi-tenant Tier 0 (ADR 0093): global scope obrigatório.
 *
 * @property int          $id
 * @property int          $business_id
 * @property int|null     $transaction_id
 * @property int          $vehicle_id
 * @property string       $order_type
 * @property string|null  $delivery_address
 * @property \Illuminate\Support\Carbon|null $expected_return_date
 * @property string|null  $daily_rate
 * @property int|null     $mileage_at_service
 * @property string       $status
 * @property \Illuminate\Support\Carbon|null $entered_at
 * @property \Illuminate\Support\Carbon|null $expected_completion
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property string|null  $notes
 *
 * @property-read bool    $is_overdue       Accessor — locação ativa com expected_return_date passada
 * @property-read int     $dias_locacao     Accessor — dias decorridos desde entered_at
 * @property-read float   $valor_receber    Accessor — daily_rate × dias_locacao
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-001
 */
class ServiceOrder extends Model
{
    use HasArquivos; // Gap 1 (2026-05-26) — anexo OS-level laudo geral / foto chassi via Modules/Arquivos backbone (ADR 0123). Complementa foto inline por item DVI (OaInspectionItem).
    use LogsActivity; // D7.b LGPD audit trail (Wave 14)
    use SoftDeletes;

    protected $table = 'service_orders';

    protected $fillable = [
        'business_id',
        'transaction_id',
        'vehicle_id',
        'order_type',
        'delivery_address',
        'expected_return_date',
        'daily_rate',
        'mileage_at_service',
        'box_label',           // Wave 2.1 US-OFICINA-027 — texto livre "Elevador 1"
        'assigned_user_id',    // Wave 2.1 US-OFICINA-027 — mecânico responsável (FK lógica)
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
        'assigned_user_id'      => 'integer',  // Wave 2.1 US-OFICINA-027
        'daily_rate'            => 'decimal:2',
        'expected_return_date'  => 'date',
        'entered_at'            => 'datetime',
        'expected_completion'   => 'datetime',
        'completed_at'          => 'datetime',
        'delivered_at'          => 'datetime',
    ];

    /** Stages considerados "ativos" pra locação (não-terminais). */
    private const RENTAL_ACTIVE_STATUSES = ['aberta', 'locada', 'em_servico'];

    /** Stages terminais (concluida, cancelada, recolhida). */
    private const RENTAL_TERMINAL_STATUSES = ['concluida', 'cancelada', 'recolhida'];

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

    /**
     * Cliente da OS — pode diferir de vehicle.contact_id em locações
     * (caçamba do Martinho é alugada pra construtora diferente do dono).
     * Coluna contact_id adicionada na hotfix PR #730 (Wave 7+1).
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    /**
     * Itens da OS (peças + mão-de-obra + serviços terceiros) — US-OFICINA-027.
     *
     * Schema `oficina_service_order_items` migrado git desde 2026-05-17 (Wave 27 G1),
     * Model + Service + Pest entregues isolados. Esta relação completa o wire-up
     * pra Observer `computeFinalTotal` somar OS de manutenção e pra UI consumir
     * via Inertia payload (drawer Cowork canon seção "PEÇAS & MÃO DE OBRA").
     */
    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class, 'service_order_id');
    }

    /**
     * Mecânico responsável pela OS (Wave 2.1 US-OFICINA-027).
     *
     * FK lógica pra users.id sem cascade — Service layer valida existência.
     * Renderizado no drawer ServiceOrderRichSheet (Wave 2.2) KV grid modo manutenção.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'assigned_user_id');
    }

    /**
     * Itens DVI (Vistoria Digital) — Wave 3 US-OFICINA-035.
     *
     * Global scope multi-tenant em OaInspectionItem garante isolamento Tier 0.
     * Eager-load em show JSON quando UI Wave 3b consumir.
     */
    public function dviInspectionItems(): HasMany
    {
        return $this->hasMany(OaInspectionItem::class, 'service_order_id');
    }

    /**
     * Breakdown DVI agregado pra UI card (8 ok · 2 atenção · 1 crítico + R$ total).
     *
     * Accessor evita re-execução de queries quando frontend lê duas vezes.
     * NÃO eager pra evitar N+1 — usar explicitamente $order->dvi_breakdown depois
     * de $order->load('dviInspectionItems') OU chamar DviInspectionService::breakdownPorSeverity.
     *
     * @return array{ok:int, atencao:int, critico:int, total_recomendado:float}
     */
    public function getDviBreakdownAttribute(): array
    {
        $items = $this->dviInspectionItems;

        return [
            'ok'                => $items->where('severity', 'ok')->count(),
            'atencao'           => $items->where('severity', 'atencao')->count(),
            'critico'           => $items->where('severity', 'critico')->count(),
            'total_recomendado' => (float) $items->whereIn('severity', ['atencao', 'critico'])->sum('valor_recomendado'),
        ];
    }

    // ------------------------------------------------------------------
    // Accessors (UI helpers + cálculos derived)
    // ------------------------------------------------------------------

    /**
     * Locação ativa com expected_return_date passada → atrasada.
     *
     * Apenas relevante pra order_type=locacao em status não-terminal.
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->order_type !== 'locacao') {
            return false;
        }

        if ($this->expected_return_date === null) {
            return false;
        }

        if (in_array($this->status, self::RENTAL_TERMINAL_STATUSES, true)) {
            return false;
        }

        return $this->expected_return_date->isPast();
    }

    /**
     * Dias decorridos desde a entrada (entered_at) até hoje.
     *
     * Pra locação ativa: dias rodando atualmente.
     * Sem entered_at → 0.
     */
    public function getDiasLocacaoAttribute(): int
    {
        if ($this->entered_at === null) {
            return 0;
        }

        return (int) max(0, $this->entered_at->startOfDay()->diffInDays(Carbon::now()->startOfDay()));
    }

    /**
     * Valor a receber pela locação ativa = daily_rate × dias_locacao.
     *
     * Retorna 0.0 se daily_rate null OU não é locação OU já terminal.
     */
    public function getValorReceberAttribute(): float
    {
        if ($this->order_type !== 'locacao') {
            return 0.0;
        }

        if ($this->daily_rate === null) {
            return 0.0;
        }

        if (in_array($this->status, self::RENTAL_TERMINAL_STATUSES, true)) {
            return 0.0;
        }

        return round((float) $this->daily_rate * $this->dias_locacao, 2);
    }

    /**
     * Total agregado de itens da OS (US-OFICINA-027) — soma `valor_total` de
     * todos itens não-deletados. Base do `ServiceOrderObserver::computeFinalTotal`
     * pra `order_type='manutencao'` (cálculo `peça×qty + hora×horas` que substitui
     * o hardcode 0.0 pré-ADR 0194).
     */
    public function getTotalItemsAttribute(): float
    {
        return round((float) $this->items()->sum('valor_total'), 2);
    }

    // ------------------------------------------------------------------
    // LGPD audit trail (D7.b — Wave 14)
    // ------------------------------------------------------------------

    /**
     * Spatie ActivityLog — registra mudanças em campos PII-relevantes
     * (vehicle_id, contact_id, daily_rate, delivery_address, status) com
     * `logOnlyDirty`. Audit trail append-only conforme retention.php contrato.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'vehicle_id',
                'contact_id',
                'order_type',
                'status',
                'delivery_address',
                'expected_return_date',
                'daily_rate',
                'completed_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('oficinaauto.service_order');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Locações ativas (order_type=locacao + status não-terminal).
     *
     * Base do dashboard "Caçambas em campo agora".
     */
    public function scopeRentalsAtivas(Builder $query): Builder
    {
        return $query->where('order_type', 'locacao')
                     ->whereNotIn('status', self::RENTAL_TERMINAL_STATUSES);
    }
}
