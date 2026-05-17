<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ServiceOrderItem — linha de uma OS (peça, mão-de-obra ou serviço terceiro).
 *
 * Origem W27 G1 — P0 fatal CAPTERRA OficinaAuto: cliente final precisa enxergar
 * "Pastilha freio R$ [redacted Tier 0] + Troca pastilha 0.5h × R$ [redacted Tier 0] = R$ [redacted Tier 0]" pra aprovar
 * orçamento via WhatsApp+PIN (G2). Sem detalhamento = bilhete único = rejeitado.
 *
 * Schema: oficina_service_order_items (migration 2026_05_17_000010).
 *
 * Multi-tenant Tier 0 ([ADR 0093]): global scope obrigatório.
 *
 * Auto-cálculo: ao save, valor_total = quantidade × valor_unitario (Service
 * pode passar valor_total override pra descontos/promo — campo direto fillable).
 *
 * @property int          $id
 * @property int          $business_id
 * @property int          $service_order_id
 * @property string       $tipo                  peca | mao_obra | servico_terceiro
 * @property string       $descricao
 * @property string       $quantidade            decimal:3 (suporta 0.250L óleo)
 * @property string       $valor_unitario        decimal:2
 * @property string       $valor_total           decimal:2
 * @property int|null     $product_id            FK products (catálogo UltimatePOS), só pra tipo=peca
 * @property string|null  $notes
 *
 * @see memory/requisitos/OficinaAuto/CAPTERRA-FICHA.md G1
 */
class ServiceOrderItem extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'oficina_service_order_items';

    public const TIPO_PECA = 'peca';
    public const TIPO_MAO_OBRA = 'mao_obra';
    public const TIPO_SERVICO_TERCEIRO = 'servico_terceiro';

    public const TIPOS_VALIDOS = [
        self::TIPO_PECA,
        self::TIPO_MAO_OBRA,
        self::TIPO_SERVICO_TERCEIRO,
    ];

    protected $fillable = [
        'business_id',
        'service_order_id',
        'tipo',
        'descricao',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'product_id',
        'notes',
    ];

    protected $casts = [
        'business_id'      => 'integer',
        'service_order_id' => 'integer',
        'product_id'       => 'integer',
        'quantidade'       => 'decimal:3',
        'valor_unitario'   => 'decimal:2',
        'valor_total'      => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 ([ADR 0093])
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('oficina_service_order_items.business_id', $businessId);
            }
        });

        static::creating(function (ServiceOrderItem $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }

            self::recalculaValorTotalSeAusente($row);
        });

        static::updating(function (ServiceOrderItem $row) {
            if ($row->isDirty(['quantidade', 'valor_unitario']) && ! $row->isDirty('valor_total')) {
                $row->valor_total = round(
                    (float) $row->quantidade * (float) $row->valor_unitario,
                    2
                );
            }
        });
    }

    private static function recalculaValorTotalSeAusente(ServiceOrderItem $row): void
    {
        // Se chamou ::create sem valor_total → derivar de quantidade × valor_unitario
        if ((float) ($row->valor_total ?? 0) === 0.0) {
            $row->valor_total = round(
                (float) $row->quantidade * (float) $row->valor_unitario,
                2
            );
        }
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    /**
     * Produto UltimatePOS legacy (só relevante pra tipo=peca).
     * Sem FK no DB (schema legacy varia), retorna null se inexistente.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Product::class, 'product_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopePecas(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_PECA);
    }

    public function scopeMaoObra(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_MAO_OBRA);
    }

    public function scopeTerceiros(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_SERVICO_TERCEIRO);
    }

    // ------------------------------------------------------------------
    // LGPD audit trail (D7.b — Wave 14 pattern)
    // ------------------------------------------------------------------

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tipo',
                'descricao',
                'quantidade',
                'valor_unitario',
                'valor_total',
                'product_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('oficinaauto.service_order_item');
    }
}
