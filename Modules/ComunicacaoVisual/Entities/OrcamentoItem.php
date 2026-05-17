<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * OrcamentoItem — linha individual de um orçamento de comunicação visual.
 *
 * Cada item representa um produto/serviço com dimensões (largura × altura × quantidade).
 * O campo area_m2 é calculado server-side pelo Controller (US-COMVIS-001):
 *   area_m2 = largura_m × altura_m × quantidade
 *
 * O subtotal é calculado como: area_m2 × preco_unitario_m2
 *
 * material_id é opcional (null = item de descrição livre sem material do catálogo).
 * Quando preenchido, preco_unitario_m2 é sugerido do Material mas pode ser sobrescrito.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * business_id redundante mas obrigatório pro global scope funcionar sem joins.
 *
 * @property int         $id
 * @property int         $orcamento_id
 * @property int         $business_id
 * @property int|null    $material_id
 * @property string      $descricao
 * @property float|null  $largura_m
 * @property float|null  $altura_m
 * @property int         $quantidade
 * @property float|null  $area_m2
 * @property float       $preco_unitario_m2
 * @property float       $subtotal
 * @property string|null $observacoes
 * @property int         $ordem
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 */
class OrcamentoItem extends Model
{
    // Sem SoftDeletes — itens são deletados junto com o orçamento via CASCADE
    use LogsActivity;

    /**
     * Audit trail Spatie ActivityLog (Wave 26 D7 LGPD compliance).
     * Whitelist exclui observacoes (texto livre potencialmente PII). Loga dimensões e preço.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['largura_m', 'altura_m', 'quantidade', 'area_m2', 'preco_unitario_m2', 'subtotal'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comvis.orcamento_item');
    }

    protected $table = 'comvis_orcamento_itens';

    protected $fillable = [
        'orcamento_id',
        'business_id',
        'material_id',
        'descricao',
        'largura_m',
        'altura_m',
        'quantidade',
        'area_m2',
        'preco_unitario_m2',
        'subtotal',
        'observacoes',
        'ordem',
    ];

    protected $casts = [
        'orcamento_id'     => 'integer',
        'business_id'      => 'integer',
        'material_id'      => 'integer',
        'largura_m'        => 'decimal:3',
        'altura_m'         => 'decimal:3',
        'quantidade'       => 'integer',
        'area_m2'          => 'decimal:3',
        'preco_unitario_m2' => 'decimal:2',
        'subtotal'         => 'decimal:2',
        'ordem'            => 'integer',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('comvis_orcamento_itens.business_id', $businessId);
            }
        });

        static::creating(function (OrcamentoItem $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Orçamento pai deste item.
     */
    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class, 'orcamento_id');
    }

    /**
     * Material do catálogo (pode ser null em itens livres).
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Recalcula area_m2 e subtotal a partir das dimensões atuais.
     * Chamado pelo Controller antes de salvar (US-COMVIS-001 frente C).
     */
    public function recalcular(): self
    {
        if ($this->largura_m !== null && $this->altura_m !== null) {
            $this->area_m2 = round((float) $this->largura_m * (float) $this->altura_m * $this->quantidade, 3);
        }
        $area = (float) ($this->area_m2 ?? 0);
        $this->subtotal = round($area * (float) $this->preco_unitario_m2, 2);
        return $this;
    }
}
